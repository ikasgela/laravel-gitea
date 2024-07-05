<?php

namespace Ikasgela\Gitea;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GiteaClient
{
    private static $cliente = null;
    private static $headers = null;

    private static function init()
    {
        if (is_null(self::$cliente))
            self::$cliente = new Client(['base_uri' => config('gitea.url') . '/api/v1/']);

        if (is_null(self::$headers))
            self::$headers = [
                'Authorization' => 'token ' . config('gitea.token'),
                'Accept' => 'application/json',
            ];
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
        self::init();

        $request = self::$cliente->get('repos/' . $repositorio, [
            'headers' => self::$headers
        ]);

        $response = json_decode($request->getBody(), true);

        $data = self::datosRepositorio($response);

        return $data;
    }

    public static function repo_by_id($id)
    {
        self::init();

        $request = self::$cliente->get('repositories/' . $id, [
            'headers' => self::$headers
        ]);

        $response = json_decode($request->getBody(), true);

        $data = self::datosRepositorio($response);

        return $data;
    }

    public static function file($owner, $repo, $filepath, $branch)
    {
        self::init();

        $query = "repos/$owner/$repo/contents/$filepath?ref=$branch";

        $request = self::$cliente->get($query, [
            'headers' => self::$headers
        ]);

        $response = json_decode($request->getBody(), true);

        return base64_decode($response['content']);
    }

    public static function repo_first_sha($owner, $repo, $branch = 'master')
    {
        self::init();

        try {
            $query = "repos/$owner/$repo/commits?sha=$branch";

            $request = self::$cliente->get($query, [
                'headers' => self::$headers
            ]);

            $response = json_decode($request->getBody(), true);

            return $response[array_key_last($response)]['sha'];
        } catch (\Exception $e) {
            Log::error('Gitea: No se ha podido obtener el hash del primer commit.', [
                'exception' => $e->getMessage()
            ]);
        }

        return null;
    }

    public static function clone($repositorio, $username, $destino, $descripcion = null)
    {
        self::init();

        try {
            // Hacer la copia del repositorio
            $request = self::$cliente->post("repos/$repositorio/generate", [
                'headers' => self::$headers,
                'json' => [
                    "owner" => $username,
                    "name" => $destino,
                    "private" => true,
                    "git_content" => true,
                    "description" => $descripcion,
                ]
            ]);

            if ($request->getStatusCode() == 201) {
                $response = json_decode($request->getBody(), true);

                $data = self::datosRepositorio($response);

                return $data;
            }
        } catch (\Exception $e) {
            Log::error('Error al clonar el repositorio.', [
                'exception' => $e->getMessage(),
                'code' => $e->getCode(),
                'repo' => $repositorio,
                'username' => $username,
                'destino' => $destino,
            ]);

            return $e->getCode();
        }
    }

    public static function repos()
    {
        self::init();

        $request = self::$cliente->get('repos/search', [
            'headers' => self::$headers,
            'query' => [
                'limit' => 1000
            ]
        ]);
        $response = json_decode($request->getBody(), true);
        return $response['data'];
    }

    public static function uid($username)
    {
        self::init();

        $request = self::$cliente->get('users/' . $username, [
            'headers' => self::$headers,
        ]);

        $response = json_decode($request->getBody(), true);
        return $response['id'];
    }

    public static function repos_usuario($username)
    {
        self::init();

        $uid = self::uid($username);

        $request = self::$cliente->get('repos/search', [
            'headers' => self::$headers,
            'query' => [
                'limit' => 50,
                'uid' => $uid,
                'exclusive' => false,
                'sort' => 'updated',
                'order' => 'desc',
            ]
        ]);
        $response = json_decode($request->getBody(), true);
        return $response['data'];
    }

    public static function orgs_usuario($username)
    {
        self::init();

        $uid = self::uid($username);

        $request = self::$cliente->get("users/$username/orgs", [
            'headers' => self::$headers,
            'query' => [
                'limit' => 50,
            ]
        ]);

        return json_decode($request->getBody(), true);
    }

    public static function borrar()
    {
        self::init();

        $repos = self::repos();

        $total = 0;
        while (count($repos) > 0) {
            foreach ($repos as $repo) {
                self::$cliente->delete('repos/' . $repo['owner']['username'] . '/' . $repo['name'], [
                    'headers' => self::$headers
                ]);
                echo '.';
                $total++;
            }

            $repos = self::repos();
        }

        return $total;
    }

    public static function borrar_repo($id)
    {
        self::init();

        $repo = self::repo_by_id($id);

        self::$cliente->delete('repos/' . $repo['owner'] . '/' . $repo['name'], [
            'headers' => self::$headers
        ]);
    }

    public static function borrar_usuario($username)
    {
        self::init();

        try {
            // Borrar los repositorios de usuario
            $repos = self::repos_usuario($username);
            while (count($repos) > 0) {
                foreach ($repos as $repo) {
                    self::$cliente->delete('repos/' . $repo['owner']['username'] . '/' . $repo['name'], [
                        'headers' => self::$headers
                    ]);
                }

                $repos = self::repos_usuario($username);
            }

            // Quitar al usuario de las organizaciones a las que pertenezca
            $orgs = self::orgs_usuario($username);
            foreach ($orgs as $org) {
                self::$cliente->delete('orgs/' . $org['username'] . '/members/' . $username, [
                    'headers' => self::$headers
                ]);
            }

            // Borrar el usuario
            self::$cliente->delete('admin/users/' . $username, [
                'headers' => self::$headers
            ]);

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
        self::init();

        try {
            self::$cliente->post('admin/users', [
                'headers' => self::$headers,
                'json' => [
                    "email" => $email,
                    "full_name" => $name,
                    "username" => $username,
                    "password" => $password ?: Str::random(62) . '._',
                    "must_change_password" => false,
                    'visibility' => 'private',
                ]
            ]);
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
        self::init();

        try {
            self::$cliente->patch('admin/users/' . $username, [
                'headers' => self::$headers,
                'json' => [
                    'login_name' => $username,
                    'password' => $password,
                    'must_change_password' => false,
                ]
            ]);
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
        self::init();

        try {
            self::$cliente->patch('admin/users/' . $username, [
                'headers' => self::$headers,
                'json' => [
                    'email' => $email,
                    'login_name' => $username,
                    'source_id' => 0,

                    'full_name' => $full_name,
                ]
            ]);
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
        self::init();

        try {
            self::$cliente->patch('admin/users/' . $username, [
                'headers' => self::$headers,
                'json' => [
                    'email' => $email,
                    'login_name' => $username,
                    'source_id' => 0,

                    'active' => false,
                    'prohibit_login' => true,

                    'allow_create_organization' => false,
                    'allow_git_hook' => false,
                    'allow_import_local' => false,
                ]
            ]);
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
        self::init();

        try {
            self::$cliente->patch('admin/users/' . $username, [
                'headers' => self::$headers,
                'json' => [
                    'email' => $email,
                    'login_name' => $username,
                    'source_id' => 0,

                    'active' => true,
                    'prohibit_login' => false,

                    'allow_create_organization' => false,
                    'allow_git_hook' => false,
                    'allow_import_local' => false,
                ]
            ]);
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
        self::init();

        try {
            self::$cliente->patch('repos/' . $username . '/' . $repositorio, [
                'headers' => self::$headers,
                'json' => [
                    'archived' => $block,
                ]
            ]);
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
        self::init();

        $query = "repos/$owner/$repo/archive/$branch";

        $response = self::$cliente->get($query, [
            'headers' => self::$headers
        ]);

        return $response->getBody()->getContents();
    }

    public static function add_collaborator($owner, $repo, $collaborator)
    {
        self::init();

        $query = "repos/$owner/$repo/collaborators/$collaborator";

        try {
            self::$cliente->put($query, [
                'headers' => self::$headers,
                'json' => [
                    'permission' => 'write',
                ]
            ]);
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
        self::init();

        try {
            self::$cliente->patch('repos/' . $username . '/' . $repositorio, [
                'headers' => self::$headers,
                'json' => [
                    'template' => $is_template,
                ]
            ]);
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

    public static function organization($name, $owner)
    {
        self::init();

        try {
            self::$cliente->post('orgs', [
                'headers' => self::$headers,
                'json' => [
                    "username" => $name,
                    'visibility' => 'private',
                ]
            ]);
            Log::info('Gitea: Nueva organización creada.', [
                'username' => $name
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Gitea: Error al crear una nueva organización.', [
                "username" => $name,
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }
}
