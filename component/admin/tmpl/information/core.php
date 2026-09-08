<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$core = (array) ($this->info['core'] ?? []);
$coreInstalled = !empty($core['installed']);
$coreApi = !empty($core['api_available']);
$coreUi = !empty($core['ui_available']);
$coreVersion = (string) ($core['version'] ?? '');
?>
<div class="xdecaro-scope membership-core-scope dm-page dm-info">
    <div class="dm-grid dm-grid-2">
        <section class="dm-card xdecaro-card">
            <h2><?= Text::_('COM_DECAROMEMBERSHIP_PRODUCT') ?></h2>
            <dl>
                <dt><?= Text::_('COM_DECAROMEMBERSHIP_NAME') ?></dt><dd>Membership by xdecaro</dd>
                <dt><?= Text::_('COM_DECAROMEMBERSHIP_VERSION') ?></dt><dd>1.1.0</dd>
                <dt><?= Text::_('COM_DECAROMEMBERSHIP_COMPONENT') ?></dt><dd><code>com_decaromembership</code></dd>
                <dt><?= Text::_('COM_DECAROMEMBERSHIP_PACKAGE') ?></dt><dd><code>pkg_decaromembership</code></dd>
            </dl>
        </section>
        <section class="dm-card xdecaro-card">
            <h2><?= Text::_('COM_DECAROMEMBERSHIP_ENVIRONMENT') ?></h2>
            <dl>
                <dt>Joomla</dt><dd><?= $escape($this->info['environment']['joomla']) ?></dd>
                <dt>PHP</dt><dd><?= $escape($this->info['environment']['php']) ?></dd>
                <dt>Database</dt><dd><?= $escape($this->info['environment']['database']) ?></dd>
            </dl>
        </section>
    </div>

    <div class="dm-grid dm-grid-2">
        <section class="dm-card xdecaro-card">
            <h2><?= Text::_('COM_DECAROMEMBERSHIP_INCLUDED_EXTENSIONS') ?></h2>
            <div class="dm-integration-list">
                <?php foreach ($this->info['extensions'] as $extension): ?>
                    <div class="dm-row">
                        <span><strong><?= $escape($extension['name']) ?></strong><br><small><?= $escape($extension['element']) ?></small></span>
                        <span class="dm-badge xdecaro-badge <?= $extension['version'] !== '' ? 'is-ok xdecaro-badge--success' : 'is-muted' ?>"><?= $extension['version'] !== '' ? 'v' . $escape($extension['version']) : '—' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="dm-card xdecaro-card">
            <h2><?= Text::_('COM_DECAROMEMBERSHIP_UPDATES') ?></h2>
            <dl><dt><?= Text::_('COM_DECAROMEMBERSHIP_PACKAGE') ?></dt><dd><code><?= $escape($this->info['updates']['package']) ?></code></dd></dl>
        </section>
    </div>

    <section class="dm-card xdecaro-card dm-full">
        <h2><?= Text::_('COM_DECAROMEMBERSHIP_CORE_TITLE') ?></h2>
        <div class="dm-integration-list">
            <div class="dm-row"><span><strong>Core by xdecaro</strong><br><small><code>pkg_xdecarocore</code> · <?= Text::_('COM_DECAROMEMBERSHIP_OPTIONAL') ?></small></span><span class="dm-badge xdecaro-badge <?= $coreInstalled ? 'is-ok xdecaro-badge--success' : 'is-muted' ?>"><?= Text::_($coreInstalled ? 'COM_DECAROMEMBERSHIP_INSTALLED' : 'COM_DECAROMEMBERSHIP_NOT_INSTALLED') ?></span></div>
            <div class="dm-row"><span><?= Text::_('COM_DECAROMEMBERSHIP_VERSION') ?></span><strong><?= $coreVersion !== '' ? $escape($coreVersion) : '—' ?></strong></div>
            <div class="dm-row"><span><?= Text::_('COM_DECAROMEMBERSHIP_PUBLIC_API') ?></span><span class="dm-badge xdecaro-badge <?= $coreApi ? 'is-ok xdecaro-badge--success' : 'is-muted' ?>"><?= Text::_($coreApi ? 'COM_DECAROMEMBERSHIP_AVAILABLE' : 'COM_DECAROMEMBERSHIP_NOT_AVAILABLE') ?></span></div>
            <div class="dm-row"><span><?= Text::_('COM_DECAROMEMBERSHIP_SHARED_UI') ?></span><span class="dm-badge xdecaro-badge <?= $coreUi ? 'is-ok xdecaro-badge--success' : 'is-muted' ?>"><?= Text::_($coreUi ? 'COM_DECAROMEMBERSHIP_AVAILABLE' : 'COM_DECAROMEMBERSHIP_NOT_AVAILABLE') ?></span></div>
        </div>
    </section>

    <section class="dm-card xdecaro-card dm-full">
        <h2><?= Text::_('COM_DECAROMEMBERSHIP_LINKED_COMPONENTS') ?></h2>
        <div class="dm-integration-list">
            <?php foreach ($this->info['integrations'] as $integration): ?>
                <div class="dm-row"><span><?= $escape($integration['name']) ?><br><small><code><?= $escape($integration['option']) ?></code></small></span><span class="dm-badge xdecaro-badge <?= $integration['enabled'] ? 'is-ok xdecaro-badge--success' : 'is-muted' ?>"><?= Text::_($integration['enabled'] ? 'JENABLED' : 'JDISABLED') ?></span></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="dm-card xdecaro-card dm-full">
        <h2><?= Text::_('COM_DECAROMEMBERSHIP_DIAGNOSTICS') ?></h2>
        <div class="dm-integration-list">
            <?php foreach ($this->info['diagnostics'] as $check): ?>
                <div class="dm-row"><code><?= $escape($check['name']) ?></code><span class="dm-badge xdecaro-badge <?= $check['ok'] ? 'is-ok xdecaro-badge--success' : 'is-bad xdecaro-badge--danger' ?>"><?= Text::_($check['ok'] ? 'JYES' : 'JNO') ?></span></div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
