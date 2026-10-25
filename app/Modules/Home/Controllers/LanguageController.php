<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Home\Controllers;

use SoulCMS\Http\Controller;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Support\Lang;

final class LanguageController extends Controller
{
    public function switch(Request $request): Response
    {
        if ($this->currentRequest()->getMethod() !== 'GET') {
            return Response::html(Lang::get('error.method_not_allowed'), 405);
        }

        $locale = (string) $this->query('locale', '');
        if (!in_array($locale, ['vi', 'en'], true)) {
            return Response::html(Lang::get('language.unsupported'), 422);
        }

        // This changes only a browser preference; GET keeps anonymous views session-free.
        $basePath = trim((string) $this->config->get('app.url.path', ''), '/');
        $path = $basePath === '' ? '/' : '/' . $basePath . '/';
        $cookie = 'soulcms_locale=' . $locale
            . '; Path=' . $path
            . '; Max-Age=31536000; SameSite=Lax; HttpOnly'
            . ((string) $this->config->get('app.url.scheme', 'http') === 'https' ? '; Secure' : '');

        return Response::redirect(safe_url((string) $this->query('return', url('/'))))
            ->withHeader('Set-Cookie', $cookie);
    }
}
