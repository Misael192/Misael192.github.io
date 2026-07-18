<?php

declare(strict_types=1);

namespace App\Models;

/**
 * GED: documento lógico → versões imutáveis (arquivo + SHA-256) →
 * assinaturas eletrônicas com evidências.
 */
class Document extends Model
{
    protected string $table = 'documents';

    public function categories(): array
    {
        return $this->select('SELECT * FROM document_categories ORDER BY name');
    }

    public function listForCompany(int $companyId): array
    {
        return $this->select(
            'SELECT d.*, c.name AS category, e.full_name AS employee,
                    v.version AS latest_version, v.id AS version_id, v.size_bytes, v.sha256,
                    (SELECT COUNT(*) FROM document_signatures s WHERE s.version_id = v.id AND s.status = \'signed\') AS signed_count,
                    (SELECT COUNT(*) FROM document_signatures s WHERE s.version_id = v.id) AS signer_count
             FROM documents d
             JOIN document_categories c ON c.id = d.category_id
             LEFT JOIN employees e ON e.id = d.employee_id
             JOIN LATERAL (
                SELECT * FROM document_versions dv WHERE dv.document_id = d.id ORDER BY dv.version DESC LIMIT 1
             ) v ON TRUE
             WHERE d.company_id = :c AND d.status = \'active\'
             ORDER BY d.created_at DESC',
            ['c' => $companyId],
        );
    }

    /**
     * Upload com versionamento: mesmo nome + colaborador + categoria →
     * nova versão do mesmo documento; caso contrário, documento novo.
     *
     * @return array{document_id: int, version: int}
     */
    public function storeUpload(
        int $companyId, ?int $employeeId, int $categoryId, string $name,
        string $filePath, string $mime, int $size, string $sha256, int $userId,
    ): array {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $existing = $this->selectOne(
                'SELECT id FROM documents
                 WHERE company_id = :c AND category_id = :cat AND name = :n
                   AND employee_id IS NOT DISTINCT FROM :e',
                ['c' => $companyId, 'cat' => $categoryId, 'n' => $name, 'e' => $employeeId],
            );

            if ($existing !== null) {
                $documentId = (int) $existing['id'];
                $version = 1 + (int) $this->selectOne(
                    'SELECT MAX(version) AS v FROM document_versions WHERE document_id = :d',
                    ['d' => $documentId],
                )['v'];
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO documents (company_id, employee_id, category_id, name, created_by)
                     VALUES (:c, :e, :cat, :n, :u) RETURNING id',
                );
                $stmt->execute(['c' => $companyId, 'e' => $employeeId, 'cat' => $categoryId, 'n' => $name, 'u' => $userId]);
                $documentId = (int) $stmt->fetchColumn();
                $version = 1;
            }

            $db->prepare(
                'INSERT INTO document_versions (document_id, version, file_path, mime_type, size_bytes, sha256, uploaded_by)
                 VALUES (:d, :v, :path, :mime, :size, :hash, :u)',
            )->execute(['d' => $documentId, 'v' => $version, 'path' => $filePath,
                'mime' => $mime, 'size' => $size, 'hash' => $sha256, 'u' => $userId]);

            $db->commit();

            return ['document_id' => $documentId, 'version' => $version];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function latestVersion(int $documentId, int $companyId): ?array
    {
        return $this->selectOne(
            'SELECT v.*, d.name, d.company_id, d.employee_id FROM document_versions v
             JOIN documents d ON d.id = v.document_id
             WHERE v.document_id = :d AND d.company_id = :c
             ORDER BY v.version DESC LIMIT 1',
            ['d' => $documentId, 'c' => $companyId],
        );
    }

    /** Assinatura eletrônica: aceite + hash do arquivo + IP/UA no ato. */
    public function sign(int $versionId, int $userId, string $fileHash): void
    {
        $this->execute(
            "INSERT INTO document_signatures (version_id, user_id, status, signed_at, file_hash, ip_address, user_agent)
             VALUES (:v, :u, 'signed', now(), :h, :ip, :ua)
             ON CONFLICT (version_id, user_id)
             DO UPDATE SET status = 'signed', signed_at = now(), file_hash = :h, ip_address = :ip, user_agent = :ua",
            ['v' => $versionId, 'u' => $userId, 'h' => $fileHash,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)],
        );
    }
}
