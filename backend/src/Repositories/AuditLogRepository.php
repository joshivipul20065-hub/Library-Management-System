<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;

final class AuditLogRepository extends BaseRepository implements AuditLogRepositoryInterface
{
    public function create(?int $userId, string $action, string $entity, ?int $entityId, array $metadata, string $ip): void
    {
        $this->db->insert(
            'INSERT INTO audit_logs (user_id, action, entity, entity_id, metadata, ip) VALUES (:user, :action, :entity, :entity_id, :metadata, :ip)',
            [
                'user' => $userId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'ip' => $ip,
            ]
        );
    }

    public function paginate(ListQuery $query): array
    {
        $where = ['1 = 1'];
        $params = [];
        foreach (['entity' => 'l.entity', 'action' => 'l.action'] as $key => $column) {
            if (($value = $query->filter($key)) !== null) {
                $where[] = "{$column} = :{$key}";
                $params[$key] = $value;
            }
        }
        if (($userId = $query->filter('user_id')) !== null) {
            $where[] = 'l.user_id = :user_id';
            $params['user_id'] = (int) $userId;
        }
        if (($from = $query->filter('from')) !== null) {
            $where[] = 'l.created_at >= :from_date';
            $params['from_date'] = $from . ' 00:00:00';
        }
        if (($to = $query->filter('to')) !== null) {
            $where[] = 'l.created_at <= :to_date';
            $params['to_date'] = $to . ' 23:59:59';
        }
        if (($q = $query->filter('q')) !== null) {
            $where[] = '(l.action LIKE :q1 OR u.name LIKE :q2)';
            $params += ['q1' => self::like($q), 'q2' => self::like($q)];
        }

        return $this->paginateQuery(
            'l.id, l.user_id, u.name AS user_name, u.role AS user_role, l.action, l.entity, l.entity_id, l.metadata, l.ip, l.created_at',
            'FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id WHERE ' . implode(' AND ', $where),
            $params,
            $query
        );
    }
}
