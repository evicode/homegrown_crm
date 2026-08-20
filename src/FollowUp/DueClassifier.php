<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\FollowUp;
use DateTimeImmutable;use DateTimeZone;
final class DueClassifier {
 public function __construct(private readonly DateTimeZone $timezone){}
 public function classify(DateTimeImmutable $dueAt,DateTimeImmutable $now):string{$localDue=$dueAt->setTimezone($this->timezone);$today=$now->setTimezone($this->timezone)->setTime(0,0);if($localDue<$today)return'overdue';if($localDue<$today->modify('+1 day'))return'today';return'upcoming';}
}
