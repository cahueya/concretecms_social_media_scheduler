<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<?php
/** @var array $channels */
/** @var array $telegramChats */
$types = ['telegram' => 'Telegram', 'listmonk' => 'Listmonk', 'matrix' => 'Matrix', 'webhook' => 'Generic Webhook', 'bluesky' => 'Bluesky', 'mastodon' => 'Mastodon'];
?>
<div class="card mb-4">
    <div class="card-header"><strong><?= t('Add Channel') ?></strong></div>
    <div class="card-body">
        <form method="post" action="<?= $view->action('save_channel') ?>">
            <?= $token->output('save_social_channel') ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?= t('Channel Type') ?></label>
                    <select name="channelType" id="sms-channel-type" class="form-select">
                        <?php foreach ($types as $key => $label): ?>
                            <option value="<?= h($key) ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?= t('Name') ?></label>
                    <input type="text" name="channelName" class="form-control" placeholder="<?= h(t('e.g. Telegram Main Channel')) ?>">
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <label class="form-check">
                        <input type="checkbox" name="isEnabled" value="1" class="form-check-input" checked>
                        <span class="form-check-label"><?= t('Enabled') ?></span>
                    </label>
                </div>
            </div>

            <div class="sms-config sms-config-telegram">
                <h4><?= t('Telegram') ?></h4>
                <div class="mb-3">
                    <label class="form-label"><?= t('Bot Token') ?></label>
                    <input type="password" name="telegram_bot_token" class="form-control" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('Chat / Group / Channel IDs') ?></label>
                    <textarea name="telegram_chat_ids" class="form-control" rows="4" placeholder="<?= h(t('One chat ID per line. Use Refresh Telegram Chats to discover IDs after the bot has seen messages.')) ?>"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= t('Parse Mode') ?></label>
                        <select name="telegram_parse_mode" class="form-select">
                            <option value="plain"><?= t('Plain text') ?></option>
                            <option value="HTML">HTML</option>
                            <option value="MarkdownV2">MarkdownV2</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <label class="form-check">
                            <input type="checkbox" name="telegram_disable_web_page_preview" value="1" class="form-check-input">
                            <span class="form-check-label"><?= t('Disable link previews') ?></span>
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-check">
                        <input type="checkbox" name="telegram_include_explicit_attachments" value="1" class="form-check-input">
                        <span class="form-check-label"><?= t('Also send explicit attachments as Telegram media') ?></span>
                    </label>
                    <div class="form-text"><?= t('Default is off: Telegram uses only images embedded in the editor body. Listmonk still sends explicit attachments as e-mail attachments.') ?></div>
                </div>
                <div class="border rounded p-3 mb-3 bg-light">
                    <label class="form-label"><?= t('Refresh Telegram Chats') ?></label>
                    <div class="input-group">
                        <input type="password" name="bot_token" form="sms-refresh-telegram-form" class="form-control" autocomplete="off" placeholder="<?= h(t('Bot token for refresh')) ?>">
                        <button class="btn btn-secondary" type="submit" form="sms-refresh-telegram-form"><?= t('Refresh') ?></button>
                    </div>
                    <div class="form-text"><?= t('This uses Telegram getUpdates and shows chats the bot has recently seen.') ?></div>
                    <?php if (!empty($telegramChats)): ?>
                        <table class="table table-sm mt-3 mb-0">
                            <thead><tr><th><?= t('ID') ?></th><th><?= t('Title') ?></th><th><?= t('Type') ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($telegramChats as $chat): ?>
                                <tr><td><code><?= h($chat['id']) ?></code></td><td><?= h($chat['title']) ?></td><td><?= h($chat['type']) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sms-config sms-config-listmonk d-none">
                <h4><?= t('Listmonk') ?></h4>
                <div class="mb-3"><label class="form-label"><?= t('Base URL') ?></label><input type="url" name="listmonk_base_url" class="form-control" placeholder="https://newsletter.example.com"></div>
                <div class="mb-3"><label class="form-label"><?= t('Username') ?></label><input type="text" name="listmonk_username" class="form-control"></div>
                <div class="mb-3"><label class="form-label"><?= t('Password / API Token') ?></label><input type="password" name="listmonk_password" class="form-control" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label"><?= t('List IDs') ?></label><input type="text" name="listmonk_list_ids" class="form-control" placeholder="1,2,3"></div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label"><?= t('Template ID') ?></label><input type="number" min="0" name="listmonk_template_id" class="form-control"></div>
                    <div class="col-md-4 mb-3"><label class="form-label"><?= t('From Email') ?></label><input type="email" name="listmonk_from_email" class="form-control"></div>
                    <div class="col-md-4 mb-3"><label class="form-label"><?= t('Messenger') ?></label><input type="text" name="listmonk_messenger" class="form-control" placeholder="email"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('After creating campaign') ?></label>
                    <select name="listmonk_start_status" class="form-select">
                        <option value="running"><?= t('Start immediately') ?></option>
                        <option value="draft"><?= t('Keep as draft') ?></option>
                        <option value="scheduled"><?= t('Set status to scheduled') ?></option>
                    </select>
                </div>
            </div>

            <div class="sms-config sms-config-matrix d-none">
                <h4><?= t('Matrix') ?></h4>
                <div class="mb-3"><label class="form-label"><?= t('Homeserver URL') ?></label><input type="url" name="matrix_homeserver" class="form-control" placeholder="https://matrix.example.com"></div>
                <div class="mb-3"><label class="form-label"><?= t('Access Token') ?></label><input type="password" name="matrix_access_token" class="form-control" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label"><?= t('Room IDs') ?></label><textarea name="matrix_room_ids" class="form-control" rows="4" placeholder="!roomid:example.com"></textarea></div>
                <div class="mb-3">
                    <label class="form-label"><?= t('Matrix message type') ?></label>
                    <select name="matrix_msgtype" class="form-select">
                        <option value="m.text">m.text</option>
                        <option value="m.notice">m.notice</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-check">
                        <input type="checkbox" name="matrix_include_explicit_attachments" value="1" class="form-check-input">
                        <span class="form-check-label"><?= t('Also send explicit attachments to Matrix') ?></span>
                    </label>
                    <div class="form-text"><?= t('Default is off: Matrix uses only images embedded in the editor body.') ?></div>
                </div>
            </div>

            <div class="sms-config sms-config-webhook d-none">
                <h4><?= t('Generic Webhook') ?></h4>
                <div class="mb-3"><label class="form-label"><?= t('Webhook URL') ?></label><input type="url" name="webhook_url" class="form-control" placeholder="https://n8n.example.com/webhook/social-post"></div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label"><?= t('HTTP Method') ?></label><select name="webhook_method" class="form-select"><option value="POST">POST</option><option value="PUT">PUT</option><option value="PATCH">PATCH</option></select></div>
                    <div class="col-md-4 mb-3"><label class="form-label"><?= t('Payload Mode') ?></label><select name="webhook_payload_mode" class="form-select"><option value="json">JSON</option><option value="form">Form Data</option><option value="multipart">Multipart</option></select></div>
                    <div class="col-md-4 mb-3"><label class="form-label"><?= t('Attachment Mode') ?></label><select name="webhook_attachment_mode" class="form-select"><option value="urls"><?= t('Public URLs') ?></option><option value="base64">Base64 JSON</option><option value="multipart">Multipart files</option></select></div>
                </div>
                <div class="mb-3"><label class="form-label"><?= t('Authentication') ?></label><select name="webhook_auth_type" class="form-select"><option value="none"><?= t('None / custom headers only') ?></option><option value="basic">Basic Auth</option><option value="bearer">Bearer Token</option></select><div class="form-text"><?= t('For n8n Basic Auth, choose Basic Auth and enter the webhook credential username and API token/password below.') ?></div></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label"><?= t('Basic Auth Username') ?></label><input type="text" name="webhook_basic_username" class="form-control" autocomplete="off"></div>
                    <div class="col-md-6 mb-3"><label class="form-label"><?= t('Basic Auth API Token / Password') ?></label><input type="password" name="webhook_basic_password" class="form-control" autocomplete="off"><div class="form-text"><?= t('Stored encrypted. Used to create Authorization: Basic base64(username:token).') ?></div></div>
                </div>
                <div class="mb-3"><label class="form-label"><?= t('Bearer token') ?></label><input type="password" name="webhook_auth_token" class="form-control" autocomplete="off"><div class="form-text"><?= t('Only used when Authentication is Bearer Token. Stored encrypted.') ?></div></div>
                <div class="mb-3"><label class="form-label"><?= t('Additional Headers') ?></label><textarea name="webhook_headers" class="form-control" rows="4" placeholder="X-Custom-Header: value"></textarea><div class="form-text"><?= t('One header per line, Name: Value. If you manually set Authorization here, it overrides generated Basic/Bearer auth.') ?></div></div>
            </div>


            <div class="sms-config sms-config-bluesky d-none">
                <h4><?= t('Bluesky') ?></h4>
                <div class="mb-3"><label class="form-label"><?= t('Handle') ?></label><input type="text" name="bluesky_handle" class="form-control" placeholder="yourname.bsky.social"><div class="form-text"><?= t('Use your Bluesky handle. For testing, use an App Password instead of your account password.') ?></div></div>
                <div class="mb-3"><label class="form-label"><?= t('App Password') ?></label><input type="password" name="bluesky_app_password" class="form-control" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label"><?= t('PDS Service URL') ?></label><input type="url" name="bluesky_service_url" class="form-control" placeholder="https://bsky.social"><div class="form-text"><?= t('Default: https://bsky.social. Do not use https://bsky.app; that is the web UI, not the API/PDS endpoint.') ?></div></div>
                <div class="mb-3">
                    <label class="form-check">
                        <input type="checkbox" name="bluesky_include_explicit_attachments" value="1" class="form-check-input">
                        <span class="form-check-label"><?= t('Also use explicit attachments as Bluesky images') ?></span>
                    </label>
                    <div class="form-text"><?= t('Default is off: Bluesky uses only images embedded in the editor body. Maximum 4 images; oversized images are rejected with a clear log error.') ?></div>
                </div>
            </div>


            <div class="sms-config sms-config-mastodon d-none">
                <h4><?= t('Mastodon') ?></h4>
                <div class="mb-3"><label class="form-label"><?= t('Instance URL') ?></label><input type="url" name="mastodon_instance_url" class="form-control" placeholder="https://mastodon.social"><div class="form-text"><?= t('Use the base URL of your Mastodon instance.') ?></div></div>
                <div class="mb-3"><label class="form-label"><?= t('Access Token') ?></label><input type="password" name="mastodon_access_token" class="form-control" autocomplete="off"><div class="form-text"><?= t('Create an application in Mastodon Preferences > Development with write permissions and copy the access token.') ?></div></div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label"><?= t('Visibility') ?></label><select name="mastodon_visibility" class="form-select"><option value="public"><?= t('Public') ?></option><option value="unlisted"><?= t('Unlisted') ?></option><option value="private"><?= t('Followers only') ?></option><option value="direct"><?= t('Direct') ?></option></select></div>
                    <div class="col-md-4 mb-3"><label class="form-label"><?= t('Language') ?></label><input type="text" name="mastodon_language" class="form-control" placeholder="de"><div class="form-text"><?= t('Optional ISO language code, for example de or en.') ?></div></div>
                    <div class="col-md-4 mb-3 d-flex align-items-end"><label class="form-check"><input type="checkbox" name="mastodon_sensitive" value="1" class="form-check-input"><span class="form-check-label"><?= t('Mark media as sensitive') ?></span></label></div>
                </div>
                <div class="mb-3"><label class="form-label"><?= t('Content Warning') ?></label><input type="text" name="mastodon_spoiler_text" class="form-control"><div class="form-text"><?= t('Optional spoiler text / content warning.') ?></div></div>
                <div class="mb-3">
                    <label class="form-check">
                        <input type="checkbox" name="mastodon_include_explicit_attachments" value="1" class="form-check-input">
                        <span class="form-check-label"><?= t('Also use explicit attachments as Mastodon media') ?></span>
                    </label>
                    <div class="form-text"><?= t('Default is off: Mastodon uses only images embedded in the editor body.') ?></div>
                </div>
            </div>


            <div class="ccm-dashboard-form-actions-wrapper">
                <div class="ccm-dashboard-form-actions">
                    <div class="float-end">
                        <button class="btn btn-primary" type="submit"><?= t('Save Channel') ?></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<form id="sms-refresh-telegram-form" method="post" action="<?= $view->action('refresh_telegram') ?>" class="d-none">
    <?= $token->output('refresh_telegram') ?>
</form>

<h2><?= t('Configured Channels') ?></h2>
<table class="table table-striped">
    <thead><tr><th><?= t('Type') ?></th><th><?= t('Name') ?></th><th><?= t('Endpoint URL') ?></th><th><?= t('Options') ?></th><th><?= t('Status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($channels as $channel): ?>
        <tr>
            <td><?= h($types[$channel['channelType']] ?? $channel['channelType']) ?></td>
            <td><?= h($channel['channelName']) ?></td>
            <td><?= !empty($channel['publicEndpointUrl']) ? '<code>' . h($channel['publicEndpointUrl']) . '</code>' : '<span class="text-muted">' . t('Not applicable') . '</span>' ?></td>
            <td><small class="text-muted"><?php
                $cfg = $channel['config'] ?? [];
                if ($channel['channelType'] === 'telegram') {
                    echo h(t('Parse mode: %s', $cfg['parse_mode'] ?? 'plain')); if (!empty($cfg['include_explicit_attachments'])) echo '<br>' . h(t('Explicit attachments: included')); else echo '<br>' . h(t('Media source: body images only')); 
                } elseif ($channel['channelType'] === 'listmonk') {
                    echo h(t('Lists: %s', implode(', ', (array) ($cfg['list_ids'] ?? []))));
                    if (!empty($cfg['template_id'])) echo '<br>' . h(t('Template: %s', $cfg['template_id']));
                    echo '<br>' . h(t('Status: %s', $cfg['start_status'] ?? 'running'));
                } elseif ($channel['channelType'] === 'matrix') {
                    echo h(t('Message type: %s', $cfg['msgtype'] ?? 'm.text')); if (!empty($cfg['include_explicit_attachments'])) echo '<br>' . h(t('Explicit attachments: included')); else echo '<br>' . h(t('Media source: body images only')); 
                } elseif ($channel['channelType'] === 'webhook') {
                    echo h(t('%s / %s / attachments: %s / auth: %s', $cfg['method'] ?? 'POST', $cfg['payload_mode'] ?? 'json', $cfg['attachment_mode'] ?? 'urls', $cfg['auth_type'] ?? 'none'));
                } elseif ($channel['channelType'] === 'bluesky') {
                    echo h(t('Handle: %s', $cfg['handle'] ?? '')); if (!empty($cfg['include_explicit_attachments'])) echo '<br>' . h(t('Explicit attachments: included')); else echo '<br>' . h(t('Media source: body images only')); 
                } elseif ($channel['channelType'] === 'mastodon') {
                    echo h(t('Visibility: %s', $cfg['visibility'] ?? 'public'));
                    if (!empty($cfg['language'])) echo '<br>' . h(t('Language: %s', $cfg['language'])); if (!empty($cfg['include_explicit_attachments'])) echo '<br>' . h(t('Explicit attachments: included')); else echo '<br>' . h(t('Media source: body images only')); 
                }
            ?></small></td>
            <td><?= $channel['isEnabled'] ? '<span class="badge bg-success">'.t('Enabled').'</span>' : '<span class="badge bg-secondary">'.t('Disabled').'</span>' ?></td>
            <td class="text-end">
                <form method="post" action="<?= $view->action('test_channel', $channel['id']) ?>" class="d-inline">
                    <?= $token->output('test_social_channel') ?>
                    <button class="btn btn-sm btn-outline-primary" type="submit"><?= t('Send Test') ?></button>
                </form>
                <a class="btn btn-sm btn-danger" href="<?= $view->action('delete_channel', $channel['id']) ?>?ccm_token=<?= $token->generate('delete_social_channel') ?>" onclick="return confirm('<?= h(t('Delete this channel?')) ?>')"><?= t('Delete') ?></a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script>
document.getElementById('sms-channel-type').addEventListener('change', function () {
    document.querySelectorAll('.sms-config').forEach(function (el) { el.classList.add('d-none') })
    var config = document.querySelector('.sms-config-' + this.value); if (config) { config.classList.remove('d-none'); }
})
</script>
