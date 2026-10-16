<?php

/** Read the throwaway walkthrough database. Not part of the application. */

$p = new PDO('sqlite:' . __DIR__ . '/../database/preview.sqlite');

echo "transfer_reviews:" . PHP_EOL;
foreach ($p->query('select id, request_id, recipient_email, status, sent_count, last_sent_at, code_sent_count from transfer_reviews') as $r) {
    echo '  #' . $r['id'] . ' req ' . $r['request_id'] . ' | to ' . $r['recipient_email']
        . ' | ' . $r['status'] . ' | sent ' . $r['sent_count'] . ' | ' . $r['last_sent_at'] . PHP_EOL;
}

echo "requests:" . PHP_EOL;
foreach ($p->query('select id, request_type, status from requests') as $r) {
    echo '  #' . $r['id'] . ' ' . $r['request_type'] . ' ' . $r['status'] . PHP_EOL;
}
