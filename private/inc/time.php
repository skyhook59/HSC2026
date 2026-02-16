<?php
function nowUtc(): DateTimeImmutable {
  return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}
function ptToUtcSaturdayCutoff(): DateTimeImmutable {
  $tzPT = new DateTimeZone('America/Los_Angeles');
  $sat = new DateTimeImmutable('Saturday 23:59:00', $tzPT);
  return $sat->setTimezone(new DateTimeZone('UTC'));
}
