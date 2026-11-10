<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helpers\Csv;
use App\Helpers\Database;
use App\Helpers\ListQuery;
use App\Helpers\Password;
use App\Models\AuthUser;
use App\Models\Student;
use App\Repositories\FineRepositoryInterface;
use App\Repositories\IssueRepositoryInterface;
use App\Repositories\ReservationRepositoryInterface;
use App\Repositories\StudentRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Validators\Rules;
use App\Validators\Validator;
use Psr\Http\Message\UploadedFileInterface;

final class StudentService
{
    public function __construct(
        private readonly Database $db,
        private readonly StudentRepositoryInterface $students,
        private readonly UserRepositoryInterface $users,
        private readonly IssueRepositoryInterface $issues,
        private readonly FineRepositoryInterface $fines,
        private readonly ReservationRepositoryInterface $reservations,
        private readonly ReservationQueue $queue,
        private readonly AuthService $auth,
        private readonly CirculationService $circulation,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
    ) {
    }

    /** @return Page */
    public function list(ListQuery $query): array
    {
        return $this->students->paginate($query);
    }

    /** @return Row */
    public function get(int $id): array
    {
        return $this->present($this->find($id));
    }

    /** Issue-desk lookup by roll number, including borrowing eligibility. */
    /** @return Row */
    public function lookupByRollNo(string $rollNo): array
    {
        $student = $this->students->findByRollNo($rollNo) ?? throw new NotFoundException('No student with this roll number', 'STUDENT_NOT_FOUND');

        return $this->present($student);
    }

    /**
     * Creates the user account and student profile. Without a password the student is emailed a setup link.
     *
     * @param Row $input
     * @return Row
     */
    public function create(array $input): array
    {
        $data = Validator::validate($input, Rules::student());
        if ($data['password'] !== null) {
            $this->auth->assertStrong((string) $data['password'], 'password');
        }

        $id = $this->db->transaction(function () use ($data): int {
            $this->assertUnique($data, null, null);
            $userId = $this->users->create(
                AuthUser::ROLE_STUDENT,
                (string) $data['name'],
                (string) $data['email'],
                Password::hash($data['password'] === null ? Password::random() : (string) $data['password'])
            );
            $id = $this->students->create($userId, $data);
            if ($data['password'] === null) {
                $this->auth->sendAccountSetup($userId);
            }
            $this->audit->log('student.create', 'student', $id, ['roll_no' => $data['roll_no'], 'email' => $data['email']]);

            return $id;
        });

        return $this->get($id);
    }

    /**
     * @param Row $input
     * @return Row
     */
    public function update(int $id, array $input): array
    {
        $student = $this->find($id);
        $data = Validator::validate($input, Rules::student());
        $this->db->transaction(function () use ($student, $data): void {
            $this->assertUnique($data, $student->userId, $student->id);
            $this->users->updateProfile($student->userId, (string) $data['name'], (string) $data['email']);
            $this->students->update($student->id, $data);
            if ($data['password'] !== null) {
                $this->auth->assertStrong((string) $data['password'], 'password');
                $this->users->updatePassword($student->userId, Password::hash((string) $data['password']));
                $this->auth->revokeAllSessions($student->userId);
            }
            $this->audit->log('student.update', 'student', $student->id, ['roll_no' => $data['roll_no'], 'password_changed' => $data['password'] !== null]);
        });

        return $this->get($id);
    }

    /** @return Row */
    public function setStatus(int $id, string $status): array
    {
        $student = $this->find($id);
        $this->users->setStatus($student->userId, $status);
        $this->notifications->notify(
            $student->userId,
            $status === 'suspended' ? 'Account suspended' : 'Account reactivated',
            $status === 'suspended'
                ? 'Your library account has been suspended. Please contact the library desk.'
                : 'Your library account is active again. You can borrow books.',
            true
        );
        $this->audit->log($status === 'suspended' ? 'student.suspend' : 'student.activate', 'student', $id);

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $student = $this->find($id);
        if ($this->issues->countActiveForStudent($id) > 0) {
            throw new ConflictException('The student still has books on loan', 'STUDENT_HAS_LOANS');
        }
        if ($this->fines->unpaidTotalForStudent($id) > 0) {
            throw new ConflictException('The student has unpaid fines', 'STUDENT_HAS_FINES');
        }
        $this->db->transaction(function () use ($student): void {
            foreach (array_unique($this->reservations->cancelActiveForStudent($student->id)) as $bookId) {
                $this->queue->promote($bookId);
            }
            $this->users->softDelete($student->userId);
            $this->auth->revokeAllSessions($student->userId);
            $this->audit->log('student.delete', 'student', $student->id, ['roll_no' => $student->rollNo, 'email' => $student->email]);
        });
    }

    /**
     * Columns: name, email, roll_no, department, year, phone, address. Each student receives an account-setup email.
     *
     * @return array{created: int, failed: int, errors: list<array{row: int, message: string, details: array<string, mixed>}>}
     */
    public function import(?UploadedFileInterface $file): array
    {
        $rows = Csv::parseUpload($file, ['name', 'email', 'roll_no', 'department', 'year']);
        $created = 0;
        $errors = [];
        foreach ($rows as $index => $row) {
            unset($row['password']);
            try {
                $this->create($row);
                $created++;
            } catch (ApiException $e) {
                $errors[] = ['row' => $index + 2, 'message' => $e->getMessage(), 'details' => $e->details];
            }
        }
        $this->audit->log('student.import', 'student', null, ['created' => $created, 'failed' => count($errors)]);

        return ['created' => $created, 'failed' => count($errors), 'errors' => $errors];
    }

    /** @return Page */
    public function history(int $id, ListQuery $query): array
    {
        $this->find($id);

        return $this->issues->paginate($query->withFilter('student_id', (string) $id));
    }

    /**
     * Self-service profile update (any role). Students can also change phone and address.
     *
     * @param Row $input
     */
    public function updateOwnProfile(AuthUser $user, array $input): void
    {
        $data = Validator::validate($input, Rules::profile());
        $this->users->updateProfile($user->id, (string) $data['name'], $user->email);
        if ($user->studentId !== null) {
            $this->students->updateContact(
                $user->studentId,
                $data['phone'] === null ? null : (string) $data['phone'],
                $data['address'] === null ? null : (string) $data['address']
            );
        }
    }

    private function find(int $id): Student
    {
        return $this->students->findById($id) ?? throw new NotFoundException('Student not found');
    }

    /** @return Row */
    private function present(Student $student): array
    {
        return $student->toArray() + ['eligibility' => $this->circulation->eligibility($student)];
    }

    /** @param Row $data */
    private function assertUnique(array $data, ?int $userId, ?int $studentId): void
    {
        $errors = [];
        if ($this->users->emailExists((string) $data['email'], $userId)) {
            $errors['email'] = 'This email is already registered';
        }
        if ($this->students->rollNoExists((string) $data['roll_no'], $studentId)) {
            $errors['roll_no'] = 'This roll number is already registered';
        }
        if ($errors !== []) {
            throw new ValidationException('The given data was invalid', $errors);
        }
    }
}
