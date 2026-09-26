<?php

declare(strict_types=1);

use App\Actions\Admin\AuthenticateAdminAction;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "=== A) admin rows as stored ===\n";

foreach (Admin::query()->orderBy('id')->get() as $admin) {
    printf(
        "  id=%d username=%-20s status=%-8s isActive=%-5s deleted_at=%-22s secret123=%s\n",
        $admin->id,
        $admin->username,
        (string) $admin->status,
        $admin->isActive() ? 'yes' : 'no',
        $admin->deleted_at === null ? 'null' : (string) $admin->deleted_at,
        Hash::check('secret123', (string) $admin->password) ? 'MATCH' : 'NO'
    );
}

echo "\n=== B) AuthenticateAdminAction (the exact web login path) ===\n";

foreach (['superadmin', 'financial-manager', 'employee'] as $username) {
    $result = app(AuthenticateAdminAction::class)->execute($username, 'secret123');
    printf("  %-20s => %s\n", $username, $result === null ? 'REJECTED' : 'ACCEPTED id='.$result->id);
}

echo "\n=== C) real HTTP login flow ===\n";

$base = 'http://localhost';
$jar = [];

$cookiesOf = static function (Symfony\Component\HttpFoundation\Response $response): array {
    $out = [];

    foreach ($response->headers->getCookies() as $cookie) {
        $out[$cookie->getName()] = $cookie->getValue();
    }

    return $out;
};

$response = $kernel->handle(Request::create($base.'/admin/login', 'GET', [], $jar));
$jar += $cookiesOf($response);
$token = preg_match('/name="_token"\s+value="([^"]+)"/', (string) $response->getContent(), $m) === 1 ? $m[1] : null;
echo '  GET  /admin/login      -> '.$response->getStatusCode().'  csrf='.($token === null ? 'MISSING' : 'ok')."\n";

$response = $kernel->handle(Request::create($base.'/admin/login', 'POST', [
    '_token' => $token,
    'username' => 'superadmin',
    'password' => 'secret123',
], $jar));
$jar += $cookiesOf($response);
echo '  POST /admin/login      -> '.$response->getStatusCode().'  redirect='.($response->headers->get('Location') ?? '-')."\n";

$response = $kernel->handle(Request::create($base.'/admin/dashboard', 'GET', [], $jar));
echo '  GET  /admin/dashboard  -> '.$response->getStatusCode()."\n";

if ($response->getStatusCode() !== 200) {
    echo "\n  follow redirect to see the error page:\n";
    $response = $kernel->handle(Request::create($base.'/admin/login', 'GET', [], $jar));
    $body = (string) $response->getContent();

    if (preg_match_all('/<div[^>]*alert[^>]*>\s*(.*?)\s*<\/div>/s', $body, $alerts) > 0) {
        foreach ($alerts[1] as $alert) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($alert)));
            echo '    alert: '.$text."\n";
        }
    }
}

echo "\n=== D) sessions rows now ===\n";

foreach (Illuminate\Support\Facades\DB::table('sessions')->get() as $session) {
    printf("  id=%s user_id=%s ip=%s\n", $session->id, $session->user_id ?? 'null', $session->ip_address ?? '-');
}
