<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$dependencyBadge = static fn(array $dependency): string => ($dependency['available'] ?? false) ? 'is-ok' : 'is-bad';
?>
<div class="dm-page dm-info">
    <div class="dm-grid dm-grid-2">
        <section class="dm-card"><h2><?= Text::_('COM_DECAROMEMBERSHIP_PRODUCT') ?></h2><dl><dt><?= Text::_('COM_DECAROMEMBERSHIP_NAME') ?></dt><dd>Membership</dd><dt><?= Text::_('COM_DECAROMEMBERSHIP_VERSION') ?></dt><dd><?= $escape($this->info['extensions'][0]['version'] ?? '1.5.0') ?></dd><dt><?= Text::_('COM_DECAROMEMBERSHIP_COMPONENT') ?></dt><dd>com_decaromembership</dd><dt><?= Text::_('COM_DECAROMEMBERSHIP_PACKAGE') ?></dt><dd>pkg_decaromembership</dd></dl></section>
        <section class="dm-card"><h2><?= Text::_('COM_DECAROMEMBERSHIP_ENVIRONMENT') ?></h2><dl><dt>Joomla</dt><dd><?= $escape($this->info['environment']['joomla']) ?></dd><dt>PHP</dt><dd><?= $escape($this->info['environment']['php']) ?></dd><dt>Database</dt><dd><?= $escape($this->info['environment']['database']) ?></dd></dl></section>
    </div>
    <div class="dm-grid dm-grid-2">
        <section class="dm-card"><h2><?= Text::_('COM_DECAROMEMBERSHIP_INCLUDED_EXTENSIONS') ?></h2><div class="dm-integration-list"><?php foreach ($this->info['extensions'] as $extension): ?><div class="dm-row"><span><strong><?= $escape($extension['name']) ?></strong><br><small><?= $escape($extension['element']) ?></small></span><span class="dm-badge is-ok">v<?= $escape($extension['version']) ?></span></div><?php endforeach; ?></div></section>
        <section class="dm-card"><h2><?= Text::_('COM_DECAROMEMBERSHIP_UPDATES') ?></h2><dl><dt><?= Text::_('COM_DECAROMEMBERSHIP_PACKAGE') ?></dt><dd><?= $escape($this->info['updates']['package']) ?></dd></dl></section>
    </div>
    <section class="dm-card dm-full"><h2><?= Text::_('COM_DECAROMEMBERSHIP_DEPENDENCIES') ?></h2><div class="dm-integration-list">
        <?php foreach (['core', 'people'] as $dependencyKey): $dependency = $this->info[$dependencyKey]; ?>
            <div class="dm-row"><span><strong><?= $escape($dependency['name']) ?></strong><br><small><?= $escape($dependency['element']) ?> · <?= Text::_('COM_DECAROMEMBERSHIP_MINIMUM_VERSION') ?> <?= $escape($dependency['minimum_version']) ?></small></span><span class="dm-badge <?= $dependencyBadge($dependency) ?>"><?= ($dependency['available'] ?? false) ? 'v'.$escape($dependency['version']) : Text::_('COM_DECAROMEMBERSHIP_NOT_AVAILABLE') ?></span></div>
        <?php endforeach; ?>
    </div></section>
    <section class="dm-card dm-full"><h2><?= Text::_('COM_DECAROMEMBERSHIP_LINKED_COMPONENTS') ?></h2><div class="dm-integration-list"><?php foreach($this->info['integrations'] as $integration): ?><div class="dm-row"><span><?= $escape($integration['name']) ?></span><span class="dm-badge <?= $integration['enabled']?'is-ok':'is-muted' ?>"><?= Text::_($integration['enabled']?'JENABLED':'JDISABLED') ?></span></div><?php endforeach; ?></div></section>
    <section class="dm-card dm-full"><h2><?= Text::_('COM_DECAROMEMBERSHIP_DIAGNOSTICS') ?></h2><div class="dm-integration-list"><?php foreach($this->info['diagnostics'] as $check): ?><div class="dm-row"><code><?= $escape($check['name']) ?></code><span class="dm-badge <?= $check['ok']?'is-ok':'is-bad' ?>"><?= Text::_($check['ok']?'JYES':'JNO') ?></span></div><?php endforeach; ?></div></section>
</div>
