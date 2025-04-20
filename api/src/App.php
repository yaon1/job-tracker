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
        $status = static function (mixed $value): Status {
            return (is_string($value) ? Status::tryFrom($value) : null) ?? throw HttpException::validation(['status' => 'Неизвестный статус']);
        };

        return (new Router())
            ->add('GET', '/api/statuses', fn () => Response::json(array_map(
                static fn (Status $s) => ['value' => $s->value, 'label' => $s->label()],
                Status::cases(),
            )))
            ->add('GET', '/api/applications', fn (Request $r) => Response::json($repo->list(
                isset($r->query['status']) ? $status($r->query['status']) : null,
                trim($r->query['q'] ?? ''),
            )))
            ->add('POST', '/api/applications', fn (Request $r) => Response::json($repo->create(ApplicationInput::fromArray($r->json())), 201))
            ->add('GET', '/api/applications/{id}', fn (Request $r) => Response::json($repo->get($r->intParam('id'))))
            ->add('PUT', '/api/applications/{id}', fn (Request $r) => Response::json($repo->update($r->intParam('id'), ApplicationInput::fromArray($r->json()))))
            ->add('PATCH', '/api/applications/{id}/status', fn (Request $r) => Response::json($repo->moveTo($r->intParam('id'), $status($r->json()['status'] ?? null))))
            ->add('DELETE', '/api/applications/{id}', function (Request $r) use ($repo) {
                $repo->delete($r->intParam('id'));

                return Response::noContent();
            })
            ->add('GET', '/api/applications/{id}/history', fn (Request $r) => Response::json($repo->history($r->intParam('id'))))
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
            ->add('GET', '/api/stats', fn () => Response::json((new Stats($pdo))->summary()));
    }
}
