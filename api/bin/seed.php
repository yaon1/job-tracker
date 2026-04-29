<?php

// Fills an empty database with realistic demo data: php bin/seed.php [path/to/db.sqlite]

declare(strict_types=1);

use JobTracker\ActivityLog;
use JobTracker\ApplicationInput;
use JobTracker\ApplicationRepository;
use JobTracker\ContactRepository;
use JobTracker\Database;
use JobTracker\InterviewRepository;
use JobTracker\Status;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = $argv[1] ?? (getenv('DATABASE_PATH') ?: dirname(__DIR__) . '/data.sqlite');
$pdo = Database::connect("sqlite:$path");
$repo = new ApplicationRepository($pdo);
if ($repo->list() !== []) {
    fwrite(STDERR, "Database already has applications, not seeding.\n");
    exit(1);
}

$day = static fn (int $d) => new DateTimeImmutable("$d days");
$apps = [
    new ApplicationInput('Северсталь Диджитал', 'Senior PHP Developer', 'https://hh.ru/vacancy/1', 280_000, 350_000, 'RUR', Status::Interview, nextActionAt: $day(2), resume: 'backend'),
    new ApplicationInput('Авито', 'Backend Engineer (PHP/Go)', null, 300_000, null, 'RUR', Status::Applied, nextActionAt: $day(-1), resume: 'backend'),
    new ApplicationInput('Т-Банк', 'PHP Team Lead', null, 400_000, 450_000, 'RUR', Status::Offer, resume: 'lead'),
    new ApplicationInput('СберМаркет', 'Fullstack (Vue/PHP)', null, 250_000, 300_000, 'RUR', Status::Rejected, resume: 'fullstack'),
    new ApplicationInput('Startup Inc', 'Founding Engineer', null, null, null, null, Status::Wishlist),
];
$ids = array_map(static fn (ApplicationInput $a) => (int) $repo->create($a)['id'], $apps);

$repo->setTags($ids[0], ['remote', 'industry']);
$repo->setTags($ids[2], ['fintech']);
(new ContactRepository($pdo))->add($ids[0], ['name' => 'Мария', 'role' => 'HR', 'telegram' => 'maria_hr']);
(new InterviewRepository($pdo))->schedule($ids[0], ['scheduled_at' => $day(2)->setTime(15, 0)->format(DATE_ATOM), 'kind' => 'tech', 'location' => 'https://meet.example/abc']);
(new ActivityLog($pdo))->add($ids[1], 'Отправил отклик с сопроводительным письмом');

echo 'Seeded ' . count($ids) . " applications into $path\n";
