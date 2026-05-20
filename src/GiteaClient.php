<?php

namespace Ikasgela\Gitea;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GiteaClient
{
    private static function http(): PendingRequest
    {
        return Http::baseUrl(config('gitea.url') . '/api/v1/')
            ->withHeaders([
                'Authorization' => 'token ' . config('gitea.token'),
                'Accept' => 'application/json',
            ]);
    }

    private static function datosRepositorio(mixed $response): array
    {
        return [
            'id' => $response['id'],
            'name' => $response['name'],
            'description' => $response['description'],
            'http_url_to_repo' => $response['clone_url'],
            'path_with_namespace' => $response['full_name'],
            'web_url' => $response['html_url'],
            'owner' => $response['owner']['login'],
            'template' => $response['template'],
        ];
    }

    public static function repo($repositorio)
    {
        try {
            $response = self::http()->get('repos/' . $repositorio);

            if ($response->failed()) {
                Log::error('Gitea: No se ha podido obtener el repositorio.', [
                    'repositorio' => $repositorio,
                    'status' => $response->status(),
                ]);
                return null;
            }

            return self::datosRepositorio($response->json());
        } catch (\Exception $e) {
            Log::error('Gitea: No se ha podido obtener el repositorio.', [
                'repositorio' => $repositorio,
                'exception' => $e->getMessage()
            ]);
        }

        return null;
    }

    public static function repo_by_id($id)
    {
        try {
            $response = self::http()->get('repositories/' . $id);

            if ($response->failed()) {
                Log::error('Gitea: No se ha podido obtener el repositorio por id.', [
                    'id' => $id,
                    'status' => $response->status(),
                ]);
                return null;
            }

            return self::datosRepositorio($response->json());
        } catch (\Exception $e) {
            Log::error('Gitea: No se ha podido obtener el repositorio por id.', [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);
        }

        return null;
    }

    public static function file($owner, $repo, $filepath, $branch)
    {
        try {
            $response = self::http()->get(
                'repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/contents/' . rawurlencode($filepath),
                ['ref' => $branch]
            );

            if ($response->failed()) {
                Log::error('Gitea: No se ha podido obtener el fichero.', [
                    'owner' => $owner,
                    'repo' => $repo,
                    'filepath' => $filepath,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $content = $response->json('content');
            if (!is_string($content)) {
                Log::error('Gitea: La respuesta del fichero no contiene contenido en base64.', [
                    'owner' => $owner,
                    'repo' => $repo,
                    'filepath' => $filepath,
                ]);
                return null;
            }

            return base64_decode($content);
        } catch (\Exception $e) {
            Log::error('Gitea: No se ha podido obtener el fichero.', [
                'owner' => $owner,
                'repo' => $repo,
                'filepath' => $filepath,
                'exception' => $e->getMessage()
            ]);
        }

        return null;
    }

    public static function repo_first_sha($owner, $repo, $branch = 'master')
    {
        try {
            // Obtener el total de commits con una petición mínima
            $base = 'repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/commits';
            $firstPage = self::http()->get($base, [
                'sha' => $branch, 'limit' => 1, 'page' => 1,
            ]);

            $total = (int) ($firstPage->header('X-Total-Count') ?? 0);

            if ($total === 0) {
                return null;
            }

            // Pedir sólo el último commit (el más antiguo, es decir, el primero)
            $lastPage = self::http()->get($base, [
                'sha' => $branch, 'limit' => 1, 'page' => $total,
            ]);

            return $lastPage->json('0.sha');
        } catch (\Exception $e) {
            Log::error('Gitea: No se ha podido obtener el hash del primer commit.', [
                'exception' => $e->getMessage()
            ]);
        }

        return null;
    }

    public static function clone($repositorio, $username, $destino, $descripcion = null)
    {
        try {
            $response = self::http()
                ->timeout(config('gitea.clone_timeout', 300))
                ->post("repos/$repositorio/generate", [
                'owner' => $username,
                'name' => $destino,
                'private' => true,
                'git_content' => true,
                'description' => $descripcion,
            ]);

            if ($response->status() === 201) {
                return self::datosRepositorio($response->json());
            }

            if ($response->status() === 409) {
                return 409;
            }

            Log::error('Error al clonar el repositorio.', [
                'status' => $response->status(),
                'repo' => $repositorio,
                'username' => $username,
                'destino' => $destino,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error al clonar el repositorio.', [
                'exception' => $e->getMessage(),
                'repo' => $repositorio,
                'username' => $username,
                'destino' => $destino,
            ]);

            return null;
        }
    }

    public static function repos(): array
    {
        $repos = [];
        $page = 1;
        do {
            $response = self::http()->get('repos/search', ['limit' => 50, 'page' => $page]);
            $data = $response->json('data') ?? [];
            $repos = array_merge($repos, $data);
            $page++;
        } while (count($data) === 50);

        return $repos;
    }

    public static function uid($username)
    {
        return self::http()->get('users/' . rawurlencode($username))->throw()->json('id');
    }

    public static function repos_usuario($username): array
    {
        $uid = self::uid($username);

        $repos = [];
        $page = 1;
        do {
            $response = self::http()->get('repos/search', [
                'limit' => 50,
                'page' => $page,
                'uid' => $uid,
                'exclusive' => false,
                'sort' => 'updated',
                'order' => 'desc',
            ]);
            $data = $response->json('data') ?? [];
            $repos = array_merge($repos, $data);
            $page++;
        } while (count($data) === 50);

        return $repos;
    }

    public static function orgs_usuario($username)
    {
        return self::http()->get('users/' . rawurlencode($username) . '/orgs', ['limit' => 50])->throw()->json();
    }

    public static function borrar()
    {
        $repos = self::repos();

        $total = 0;
        $prevCount = PHP_INT_MAX;
        while (count($repos) > 0) {
            if (count($repos) >= $prevCount) {
                Log::error('Gitea: Borrado interrumpido, los repositorios no disminuyen.', [
                    'count' => count($repos)
                ]);
                break;
            }
            $prevCount = count($repos);

            foreach ($repos as $repo) {
                self::http()->delete('repos/' . rawurlencode($repo['owner']['username']) . '/' . rawurlencode($repo['name']));
                echo '.';
                $total++;
            }

            $repos = self::repos();
        }

        return $total;
    }

    public static function borrar_repo($id)
    {
        try {
            $repo = self::repo_by_id($id);

            if (is_null($repo)) {
                return;
            }

            self::http()->delete('repos/' . rawurlencode($repo['owner']) . '/' . rawurlencode($repo['name']));
        } catch (\Exception $e) {
            Log::error('Gitea: No se ha podido borrar el repositorio.', [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);
        }
    }

    public static function borrar_usuario($username)
    {
        try {
            // Borrar los repositorios de usuario
            $repos = self::repos_usuario($username);
            $prevCount = PHP_INT_MAX;
            while (count($repos) > 0) {
                if (count($repos) >= $prevCount) {
                    Log::error('Gitea: Borrado de usuario interrumpido, los repositorios no disminuyen.', [
                        'username' => $username,
                        'count' => count($repos)
                    ]);
                    break;
                }
                $prevCount = count($repos);

                foreach ($repos as $repo) {
                    self::http()->delete('repos/' . rawurlencode($repo['owner']['username']) . '/' . rawurlencode($repo['name']));
                }

                $repos = self::repos_usuario($username);
            }

            // Quitar al usuario de las organizaciones a las que pertenezca
            $orgs = self::orgs_usuario($username);
            foreach ($orgs as $org) {
                self::http()->delete('orgs/' . rawurlencode($org['username']) . '/members/' . rawurlencode($username));
            }

            // Borrar el usuario
            self::http()->delete('admin/users/' . rawurlencode($username));

            Log::info('Gitea: Usuario borrado.', [
                'username' => $username
            ]);
        } catch (\Exception $e) {
            Log::error('Gitea: Error al borrar el usuario.', [
                'username' => $username,
                'exception' => $e->getMessage()
            ]);
        }
    }

    public static function user($email, $username, $name, $password = null)
    {
        try {
            self::http()->post('admin/users', [
                'email' => $email,
                'full_name' => $name,
                'username' => $username,
                'password' => $password ?: Str::random(62) . '._',
                'must_change_password' => false,
                'visibility' => 'private',
            ])->throw();

            Log::info('Gitea: Nuevo usuario creado.', [
                'username' => $username
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Gitea: Error al crear un nuevo usuario.', [
                'username' => $username,
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    public static function password($username, $password)
    {
        try {
            self::http()->patch('admin/users/' . rawurlencode($username), [
                'login_name' => $username,
                'password' => $password,
                'must_change_password' => false,
            ])->throw();

            Log::info('Gitea: Contraseña cambiada.', [
                'username' => $username
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Gitea: Error al cambiar la contraseña.', [
                'username' => $username,
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    public static function full_name($email, $username, $full_name)
    {
        try {
            self::http()->patch('admin/users/' . rawurlencode($username), [
                'email' => $email,
                'login_name' => $username,
                'source_id' => 0,
                'full_name' => $full_name,
            ])->throw();

            Log::info('Gitea: Nombre actualizado.', [
                'username' => $username
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Gitea: Error al actualizar el nombre.', [
                'username' => $username,
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    public static function block($email, $username)
    {
        try {
            self::http()->patch('admin/users/' . rawurlencode($username), [
                'email' => $email,
                'login_name' => $username,
                'source_id' => 0,
                'active' => false,
                'prohibit_login' => true,
                'allow_create_organization' => false,
                'allow_git_hook' => false,
                'allow_import_local' => false,
            ])->throw();

            Log::info('Gitea: Usuario bloqueado.', [
                'username' => $username
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Gitea: Error al bloquear un usuario.', [
                'username' => $username,
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    public static function unblock($email, $username)
    {
        try {
            self::http()->patch('admin/users/' . rawurlencode($username), [
                'email' => $email,
                'login_name' => $username,
                'source_id' => 0,
                'active' => true,
                'prohibit_login' => false,
                'allow_create_organization' => false,
                'allow_git_hook' => false,
                'allow_import_local' => false,
            ])->throw();

            Log::info('Gitea: Usuario desbloqueado.', [
                'username' => $username
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Gitea: Error al desbloquear un usuario.', [
                'username' => $username,
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    public static function block_repo($username, $repositorio, $block = true)
    {
        try {
            self::http()->patch('repos/' . rawurlencode($username) . '/' . rawurlencode($repositorio), [
                'archived' => $block,
            ])->throw();

            Log::info('Gitea: Repositorio ' . ($block ? 'bloqueado' : 'desbloqueado') . '.', [
                'username' => $username,
                'repository' => $repositorio,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Gitea: Error al bloquear/desbloquear un repositorio.', [
                'username' => $username,
                'repository' => $repositorio,
                'archived' => $block,
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    public static function download($owner, $repo, $branch)
    {
        try {
            $response = self::http()
                ->withOptions(['stream' => true])
                ->get('repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/archive/' . rawurlencode($branch));

            if ($response->failed()) {
                Log::error('Gitea: No se ha podido descargar el repositorio.', [
                    'owner' => $owner,
                    'repo' => $repo,
                    'branch' => $branch,
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->toPsrResponse()->getBody();
        } catch (\Exception $e) {
            Log::error('Gitea: No se ha podido descargar el repositorio.', [
                'owner' => $owner,
                'repo' => $repo,
                'branch' => $branch,
                'exception' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public static function add_collaborator($owner, $repo, $collaborator)
    {
        try {
            self::http()->put('repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/collaborators/' . rawurlencode($collaborator), [
                'permission' => 'write',
            ])->throw();

            return true;
        } catch (\Exception $e) {
            Log::error('Gitea: Error al añadir colaborador.', [
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    public static function template($username, $repositorio, $is_template = true)
    {
        try {
            self::http()->patch('repos/' . rawurlencode($username) . '/' . rawurlencode($repositorio), [
                'template' => $is_template,
            ])->throw();

            Log::info('Gitea: Repositorio ' . ($is_template ? 'marcado como plantilla' : 'desmarcado como plantilla') . '.', [
                'username' => $username,
                'repository' => $repositorio,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Gitea: Error al marcar/desmarcar un repositorio como plantilla.', [
                'username' => $username,
                'repository' => $repositorio,
                'template' => $is_template,
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    public static function organization($name, $full_name)
    {
        try {
            // Comprobar si ya existe usando el endpoint directo, sin paginar todas las orgs
            $check = self::http()->get('orgs/' . rawurlencode($name));

            if ($check->ok()) {
                Log::info('Gitea: La organización ya existe.', ['username' => $name]);
                return true;
            }

            if (!$check->notFound()) {
                Log::error('Gitea: Error inesperado al comprobar la organización.', [
                    'username' => $name,
                    'status' => $check->status(),
                ]);
                return false;
            }

            // 404 = no existe, se crea a continuación
            self::http()->post('orgs', [
                'username' => $name,
                'full_name' => $full_name,
                'visibility' => 'private',
            ])->throw();

            Log::info('Gitea: Nueva organización creada.', ['username' => $name]);
            return true;
        } catch (\Exception $e) {
            Log::error('Gitea: Error al crear una nueva organización.', [
                'username' => $name,
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    public static function borrar_organizacion($organization)
    {
        try {
            // Borrar los repositorios de la organización
            $repos = self::repos_usuario($organization);
            $prevCount = PHP_INT_MAX;
            while (count($repos) > 0) {
                if (count($repos) >= $prevCount) {
                    Log::error('Gitea: Borrado de organización interrumpido, los repositorios no disminuyen.', [
                        'organization' => $organization,
                        'count' => count($repos)
                    ]);
                    break;
                }
                $prevCount = count($repos);

                foreach ($repos as $repo) {
                    self::http()->delete('repos/' . rawurlencode($repo['owner']['username']) . '/' . rawurlencode($repo['name']));
                }

                $repos = self::repos_usuario($organization);
            }

            // Borrar la organización
            self::http()->delete('orgs/' . rawurlencode($organization));

            Log::info('Gitea: Organización borrada.', ['organization' => $organization]);
        } catch (\Exception $e) {
            Log::error('Gitea: Error al borrar la organización.', [
                'organization' => $organization,
                'exception' => $e->getMessage()
            ]);
        }
    }
}
