<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Auth;
use App\Middleware\Csrf;
use App\Models\Database;
use App\Services\Ai\CltAssistantService;

/** Chat do Assistente CLT — disponível a todo usuário logado, conversa própria. */
class AssistantController
{
    public function index(): void
    {
        Auth::check();
        $userId = auth_user()['id'];
        $db = Database::connection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();

            if (($_POST['action'] ?? '') === 'reset') {
                $db->prepare('DELETE FROM ai_conversations WHERE user_id = :u')->execute(['u' => $userId]);
                flash('success', 'Nova conversa iniciada.');
                redirect('assistente.php');
            }

            $question = trim((string) ($_POST['message'] ?? ''));
            if ($question !== '' && mb_strlen($question) <= 500) {
                $conversationId = $this->conversation($db, $userId, $question);
                $insert = $db->prepare(
                    'INSERT INTO ai_messages (conversation_id, role, content) VALUES (:c, :r, :m)',
                );
                $insert->execute(['c' => $conversationId, 'r' => 'user', 'm' => $question]);
                $insert->execute(['c' => $conversationId, 'r' => 'assistant',
                    'm' => (new CltAssistantService)->answer($question)]);
            }
            redirect('assistente.php');
        }

        $stmt = $db->prepare(
            'SELECT m.role, m.content, m.created_at FROM ai_messages m
             JOIN ai_conversations c ON c.id = m.conversation_id
             WHERE c.user_id = :u ORDER BY m.id',
        );
        $stmt->execute(['u' => $userId]);

        view('assistant', ['messages' => $stmt->fetchAll()]);
    }

    /** Conversa corrente do usuário (cria na 1ª mensagem, título = pergunta). */
    private function conversation(\PDO $db, int $userId, string $firstQuestion): int
    {
        $stmt = $db->prepare('SELECT id FROM ai_conversations WHERE user_id = :u ORDER BY id DESC LIMIT 1');
        $stmt->execute(['u' => $userId]);
        if (($id = $stmt->fetchColumn()) !== false) {
            return (int) $id;
        }

        $stmt = $db->prepare('INSERT INTO ai_conversations (user_id, title) VALUES (:u, :t) RETURNING id');
        $stmt->execute(['u' => $userId, 't' => mb_substr($firstQuestion, 0, 120)]);

        return (int) $stmt->fetchColumn();
    }
}
