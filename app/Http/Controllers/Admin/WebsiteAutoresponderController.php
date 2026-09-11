<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWebsiteAutoresponderRequest;
use App\Models\Website;
use App\Models\WebsiteMailConnection;
use App\Services\AutoresponderHtmlSanitizer;
use Illuminate\Http\RedirectResponse;

class WebsiteAutoresponderController extends Controller
{
    public function __construct(private AutoresponderHtmlSanitizer $autoresponderHtmlSanitizer) {}

    public function __invoke(UpdateWebsiteAutoresponderRequest $request, Website $website): RedirectResponse
    {
        $settings = $request->validated();
        $mailDeliveryMode = $settings['mail_delivery_mode'];
        $postmarkServerToken = $settings['postmark_server_token'] ?? null;
        unset($settings['mail_delivery_mode'], $settings['postmark_server_token']);
        if ($settings['autoresponder_content_type'] === 'text') {
            $settings['autoresponder_body'] = $this->autoresponderHtmlSanitizer->sanitize($settings['autoresponder_body'] ?? null);
        }

        $website->update($settings);

        $connection = $website->mailConnection()->firstOrNew();
        $connection->fill([
            'mode' => $mailDeliveryMode,
            'status' => $mailDeliveryMode === WebsiteMailConnection::MODE_MANAGED && ! $connection->dkim_verified
                ? 'pending_verification'
                : 'active',
            'connected_at' => $connection->connected_at ?? now(),
            'paused_at' => null,
            'pause_reason' => null,
        ]);

        if ($mailDeliveryMode === WebsiteMailConnection::MODE_CUSTOMER_POSTMARK && filled($postmarkServerToken)) {
            $connection->postmark_server_token = $postmarkServerToken;
        }

        $connection->save();

        return redirect()->route('admin.websites.show', $website)->with('status', 'Automatic reply settings updated.');
    }
}
