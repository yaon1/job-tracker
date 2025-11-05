<?php

declare(strict_types=1);

namespace JobTracker;

use JobTracker\Http\HttpException;
use JobTracker\Http\Request;
use JobTracker\Http\Response;
use JobTracker\Http\Router;

final class App
{
    public static function routes(\PDO $pdo, HhImporter $hh): Router
    {
        $repo = new ApplicationRepository($pdo);
        $contacts = new ContactRepository($pdo);
        $interviews = new InterviewRepository($pdo);
        $status = static function (mixed $value): Status {
            return (is_string($value) ? Status::tryFrom($value) : null) ?? throw HttpException::validation(['status' => 'Неизвестный статус']);
        };

        return (new Router())
            ->add('GET', '/api/statuses', fn() => Response::json(array_map(
                static fn(Status $s) => ['value' => $s->value, 'label' => $s->label()],
                Status::cases(),
            )))
            ->add('GET', '/api/applications', fn(Request $r) => Response::json($repo->list(filter: ListFilter::fromQuery($r->query))))
            ->add('POST', '/api/applications', fn(Request $r) => Response::json($repo->create(ApplicationInput::fromArray($r->json())), 201))
            ->add('GET', '/api/applications/{id}', fn(Request $r) => Response::json($repo->get($r->intParam('id'))))
            ->add('PUT', '/api/applications/{id}', fn(Request $r) => Response::json($repo->update($r->intParam('id'), ApplicationInput::fromArray($r->json()))))
            ->add('PATCH', '/api/applications/{id}/status', fn(Request $r) => Response::json($repo->moveTo($r->intParam('id'), $status($r->json()['status'] ?? null))))
            ->add('DELETE', '/api/applications/{id}', function (Request $r) use ($repo) {
                $repo->delete($r->intParam('id'));

                return Response::noContent();
            })
            ->add('GET', '/api/applications/{id}/history', fn(Request $r) => Response::json($repo->history($r->intParam('id'))))
            ->add('POST', '/api/import/hh', function (Request $r) use ($repo, $hh) {
                $url = $r->json()['url'] ?? null;
                $input = $hh->import(is_string($url) ? $url : '');
                $existing = $repo->findExternal('hh', (string) $input->externalId);
                if ($existing !== null) {
                    return Response::json(['error' => 'Эта вакансия уже в трекере', 'id' => $existing], 409);
                }

                return Response::json($repo->create($input), 201);
            })
            ->add('GET', '/api/applications/{id}/contacts', function (Request $r) use ($repo, $contacts) {
                $repo->get($r->intParam('id'));

                return Response::json($contacts->forApplication($r->intParam('id')));
            })
            ->add('POST', '/api/applications/{id}/contacts', function (Request $r) use ($repo, $contacts) {
                $repo->get($r->intParam('id'));

                return Response::json($contacts->add($r->intParam('id'), $r->json()), 201);
            })
            ->add('DELETE', '/api/applications/{id}/contacts/{contact}', function (Request $r) use ($contacts) {
                $contacts->delete($r->intParam('id'), $r->intParam('contact'));

                return Response::noContent();
            })
            ->add('POST', '/api/applications/{id}/interviews', function (Request $r) use ($repo, $interviews) {
                $repo->get($r->intParam('id'));

                return Response::json($interviews->schedule($r->intParam('id'), $r->json()), 201);
            })
            ->add('DELETE', '/api/interviews/{id}', function (Request $r) use ($interviews) {
                $interviews->cancel($r->intParam('id'));

                return Response::noContent();
            })
            ->add('GET', '/api/interviews', fn() => Response::json($interviews->upcoming(new \DateTimeImmutable('-1 day'))))
            ->add('GET', '/api/interviews.ics', fn() => new Response(
                200,
                Calendar::render($interviews->upcoming(new \DateTimeImmutable('-30 days'))),
                ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'inline; filename="interviews.ics"'],
            ))
            ->add('PUT', '/api/applications/{id}/tags', function (Request $r) use ($repo) {
                $raw = $r->json()['tags'] ?? null;
                $tags = [];
                foreach (is_array($raw) ? $raw : [null] as $tag) {
                    $tags[] = is_string($tag) ? $tag : throw HttpException::validation(['tags' => 'Список строк']);
                }

                return Response::json($repo->setTags($r->intParam('id'), $tags));
            })
            ->add('GET', '/api/export.csv', fn() => new Response(
                200,
                Csv::export($repo->list(filter: new ListFilter(limit: 500))),
                ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="applications.csv"'],
            ))
            ->add('POST', '/api/import.csv', function (Request $r) use ($repo, $pdo) {
                $inputs = Csv::import($r->body);
                // All or nothing: one bad line shouldn't leave half a file imported.
                $pdo->beginTransaction();
                try {
                    foreach ($inputs as $input) {
                        $repo->create($input);
                    }
                    $pdo->commit();
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }

                return Response::json(['imported' => count($inputs)], 201);
            })
            ->add('GET', '/api/reminders', fn() => Response::json($repo->dueBy(new \DateTimeImmutable('today'))))
            ->add('GET', '/api/tags', fn() => Response::json($repo->allTags()))
            ->add('GET', '/api/stats', fn() => Response::json((new Stats($pdo))->summary()));
    }
}
