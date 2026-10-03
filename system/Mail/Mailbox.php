<?php
namespace System\Mail;

/**
 * The emails kept by the "file" driver (and, outside production, the "log" driver) in
 * storage/mail, for System status → Mailbox.
 */
class Mailbox
{
    /**
     * An email's id is its file name without ".json": 20261003_101500_3f9c0a1b
     * (URLs must not end in ".json": PHP's built-in server would answer them itself)
     */
    protected const ID = '/^\d{8}_\d{6}_[a-f0-9]{8}$/';

    public static function path(): string
    {
        return rtrim((string) config('mail.file_path', BASE_PATH . '/storage/mail'), '/\\');
    }

    /** The newest emails first: [file, time, to, subject, attachments] */
    public static function latest(int $limit = 20): array
    {
        $files = glob(self::path() . '/*.json') ?: [];
        rsort($files);
        $list = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $mail = json_decode((string) file_get_contents($file), true) ?: [];
            $list[] = [
                'id'          => basename($file, '.json'),
                'time'        => date('Y-m-d H:i:s', (int) filemtime($file)),
                'to'          => implode(', ', array_column($mail['to'] ?? [], 0)),
                'subject'     => (string) ($mail['subject'] ?? ''),
                'attachments' => count($mail['attachments'] ?? []),
            ];
        }
        return $list;
    }

    /** One email by its id, or null (only ids the mailbox itself writes are accepted: no paths) */
    public static function find(string $id): ?array
    {
        $file = self::path() . '/' . $id . '.json';
        if (!preg_match(self::ID, $id) || !is_file($file)) {
            return null;
        }
        return json_decode((string) file_get_contents($file), true) ?: null;
    }

    /** Keep only the newest $keep emails */
    public static function prune(int $keep): void
    {
        $files = glob(self::path() . '/*.json') ?: [];
        rsort($files);
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }
}
