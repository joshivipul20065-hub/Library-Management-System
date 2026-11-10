<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Helpers\Database;
use App\Helpers\ListQuery;
use App\Helpers\RequestContext;
use App\Repositories\FineRepositoryInterface;
use App\Validators\Rules;
use App\Validators\Validator;

final class FineService
{
    public function __construct(
        private readonly Database $db,
        private readonly FineRepositoryInterface $fines,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
        private readonly RequestContext $context,
    ) {
    }

    /** @return Page */
    public function list(ListQuery $query): array
    {
        return $this->fines->paginate($query);
    }

    /** @return Row */
    public function pay(int $id): array
    {
        return $this->settle($id, 'paid', null);
    }

    /**
     * @param Row $input
     * @return Row
     */
    public function waive(int $id, array $input): array
    {
        $reason = Validator::validate($input, Rules::waive())['reason'];

        return $this->settle($id, 'waived', $reason === null ? null : (string) $reason);
    }

    /** @return Row */
    private function settle(int $id, string $status, ?string $reason): array
    {
        return $this->db->transaction(function () use ($id, $status, $reason): array {
            $fine = $this->fines->findById($id, true) ?? throw new NotFoundException('Fine not found');
            if ($fine->status !== 'unpaid') {
                throw new ConflictException("This fine is already {$fine->status}", 'FINE_ALREADY_SETTLED');
            }
            $this->fines->setStatus($fine->id, $status, $this->context->user()->id);
            $this->notifications->notify(
                $fine->studentUserId,
                $status === 'paid' ? 'Fine paid' : 'Fine waived',
                sprintf('Your fine of %.2f (%s) was %s.', $fine->amount, $fine->reason, $status)
            );
            $this->audit->log("fine.{$status}", 'fine', $fine->id, array_filter(['amount' => $fine->amount, 'reason' => $reason]));

            return ['id' => $fine->id, 'amount' => $fine->amount, 'status' => $status];
        });
    }
}
