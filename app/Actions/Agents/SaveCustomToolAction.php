<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\SaveCustomToolData;
use App\Models\CustomTool;
use App\Services\Http\UnsafeUrl;
use App\Services\Http\UrlGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Every save is a new version. The address is checked here, so a tool that points somewhere private never exists. */
class SaveCustomToolAction
{
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH'];

    public function __construct(private readonly UrlGuard $guard) {}

    public function execute(SaveCustomToolData $data): CustomTool
    {
        if (! $data->actor->canInOrganization('tools.manage', $data->organizationId)) {
            throw new AuthorizationException('You cannot manage tools in this business.');
        }

        $this->check($data);

        $version = (int) CustomTool::where('organization_id', $data->organizationId)->where('key', $data->key)->max('version') + 1;

        return CustomTool::create([
            'organization_id' => $data->organizationId,
            'key' => $data->key,
            'version' => $version,
            'label' => $data->label,
            'description' => $data->description,
            'method' => $data->method,
            'url' => $data->url,
            'headers' => $data->headers,
            'input_schema' => $data->inputSchema,
            'reads_sensitive' => $data->readsSensitive,
            'created_by' => $data->actor->id,
        ]);
    }

    private function check(SaveCustomToolData $data): void
    {
        $errors = [];

        if (! preg_match('/^[a-z0-9_]{2,40}$/', $data->key)) {
            $errors['key'] = 'Use lower-case letters, numbers and underscores.';
        }

        if (! in_array($data->method, self::METHODS, true)) {
            $errors['method'] = 'This method is not supported.';
        }

        if (($data->inputSchema['type'] ?? null) !== 'object') {
            $errors['input_schema'] = 'The inputs must be described as an object.';
        }

        $errors += $this->addressErrors($data->url, $data->inputSchema);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, string>
     */
    private function addressErrors(string $url, array $schema): array
    {
        $parts = parse_url(str_replace(['{', '}'], '', $url));
        $host = strstr((string) preg_replace('#^https://#', '', $url), '/', true) ?: (string) preg_replace('#^https://#', '', $url);

        if (($parts['scheme'] ?? null) !== 'https' || str_contains($host, '{')) {
            return ['url' => 'Use an https address with a fixed host.'];
        }

        preg_match_all('/\{(\w+)\}/', $url, $names);

        foreach ($names[1] as $name) {
            if (! isset($schema['properties'][$name])) {
                return ['url' => "{$name} is in the address but not in the inputs."];
            }
        }

        try {
            $this->guard->assertSafe((string) preg_replace('/\{\w+\}/', 'x', $url));
        } catch (UnsafeUrl $e) {
            return ['url' => $e->getMessage()];
        }

        return [];
    }
}
