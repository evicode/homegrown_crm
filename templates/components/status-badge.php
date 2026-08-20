<?php
$knownStatuses = ['new', 'researched', 'contacted', 'responded', 'qualified', 'proposal', 'won', 'lost', 'due', 'overdue', 'completed', 'cancelled'];
$statusKey = in_array($status, $knownStatuses, true) ? $status : 'new';
?>
<span class="status-badge status-badge--<?= $escape($statusKey) ?>"><?= $escape($label) ?></span>
