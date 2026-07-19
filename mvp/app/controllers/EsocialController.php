<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Database;
use App\Services\AuditService;
use App\Services\Esocial\EsocialService;

/** eSocial: geração e download dos eventos S-2200 (admissão) e S-1200 (remuneração). */
class EsocialController
{
    public function __construct(private readonly EsocialService $esocial = new EsocialService)
    {
    }

    public function index(): void
    {
        Can::check('esocial:manage');
        $companyId = auth_user()['company_id'];
        $db = Database::connection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            match ($_POST['action'] ?? '') {
                'admissions' => $this->admissions($companyId),
                'remuneration' => $this->remuneration($companyId),
                default => null,
            };
            redirect('esocial.php');
        }

        $events = $db->prepare(
            'SELECT ev.id, ev.event_type, ev.reference, ev.status, ev.created_at,
                    e.full_name, length(ev.xml) AS size
             FROM esocial_events ev
             LEFT JOIN employees e ON e.id = ev.employee_id
             WHERE ev.company_id = :c ORDER BY ev.created_at DESC',
        );
        $events->execute(['c' => $companyId]);

        $pendingAdmissions = $db->prepare(
            "SELECT count(*) FROM employees e
             WHERE e.company_id = :c AND e.status <> 'terminated'
               AND NOT EXISTS (SELECT 1 FROM esocial_events ev
                                WHERE ev.company_id = e.company_id
                                  AND ev.event_type = 'S-2200' AND ev.reference = e.registration)",
        );
        $pendingAdmissions->execute(['c' => $companyId]);

        $closedPeriods = $db->prepare(
            "SELECT competency FROM payroll_periods
             WHERE company_id = :c AND status = 'closed' ORDER BY competency DESC",
        );
        $closedPeriods->execute(['c' => $companyId]);

        view('esocial', [
            'events' => $events->fetchAll(),
            'pendingAdmissions' => (int) $pendingAdmissions->fetchColumn(),
            'closedPeriods' => array_column($closedPeriods->fetchAll(), 'competency'),
        ]);
    }

    /** Download do XML do evento (?id=). */
    public function download(): void
    {
        Can::check('esocial:manage');

        $stmt = Database::connection()->prepare(
            'SELECT * FROM esocial_events WHERE id = :id AND company_id = :c',
        );
        $stmt->execute(['id' => (int) ($_GET['id'] ?? 0), 'c' => auth_user()['company_id']]);
        $event = $stmt->fetch();

        if ($event === false) {
            http_response_code(404);
            exit('Evento não encontrado.');
        }

        $file = strtolower(str_replace('-', '', $event['event_type'])).'-'.preg_replace('/[^A-Za-z0-9_-]/', '_', $event['reference']).'.xml';
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$file.'"');
        echo $event['xml'];
        exit;
    }

    private function admissions(int $companyId): void
    {
        [$ok, $message] = $this->esocial->generateAdmissions($companyId, auth_user()['id']);
        AuditService::log('esocial.s2200', 'esocial_event', null, null, ['result' => $message]);
        flash($ok ? 'success' : 'error', $message);
    }

    private function remuneration(int $companyId): void
    {
        $competency = (string) ($_POST['competency'] ?? '');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $competency)) {
            flash('error', 'Escolha uma competência fechada.');

            return;
        }

        [$ok, $message] = $this->esocial->generateRemuneration($companyId, $competency, auth_user()['id']);
        AuditService::log('esocial.s1200', 'esocial_event', $competency, null, ['result' => $message]);
        flash($ok ? 'success' : 'error', $message);
    }
}
