<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

$action = request_action();

try {
    if ($action === 'list') {
        require_permission($pdo, 'email_templates', 'can_view');
        json_response(true, '', $pdo->query(
            "SELECT id, template_name, template_code, email_subject, status
             FROM email_templates ORDER BY template_name"
        )->fetchAll());
    }

    if ($action === 'get') {
        require_permission($pdo, 'email_templates', 'can_view');
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => (int)request_value('id')]);
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('Email template not found.');
        json_response(true, '', $row);
    }

    if ($action === 'save') {
        require_permission($pdo, 'email_templates', 'can_edit');

        $id = (int)request_value('id');
        $subject = trim((string)request_value('email_subject'));
        $body = trim((string)request_value('body_html'));
        $status = request_value('status') === 'inactive' ? 'inactive' : 'active';

        if ($subject === '' || $body === '') {
            throw new RuntimeException('Subject and body are required.');
        }

        $pdo->prepare(
            "UPDATE email_templates
             SET email_subject = :subject,
                 body_html = :body,
                 status = :status,
                 updated_by = :updated_by
             WHERE id = :id"
        )->execute([
            'subject' => $subject,
            'body' => $body,
            'status' => $status,
            'updated_by' => current_admin_id(),
            'id' => $id,
        ]);

        activity_log($pdo, 'update', 'Email Templates', 'email_template', $id, 'Email template updated.');

        json_response(true, 'Email template updated successfully.');
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    json_response(false, $e->getMessage(), null, 422);
}
