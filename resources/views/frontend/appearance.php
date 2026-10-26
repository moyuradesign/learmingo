<?php
$sections = [
 'Brand & theme' => ['brand'=>'Site name','logo_light'=>'Logo URL — light mode','logo_dark'=>'Logo URL — dark mode','default_theme'=>'Default theme'],
 'Header navigation' => ['search_hint'=>'Search placeholder','courses_label'=>'Courses label','courses_url'=>'Courses link','categories_label'=>'Categories label','categories_url'=>'Categories link','sign_in_label'=>'Sign In label','sign_up_label'=>'Sign Up label','header_search'=>'Show search','header_sticky'=>'Sticky header'],
 'Homepage' => ['hero_eyebrow'=>'Eyebrow text','hero_title'=>'Hero title','hero_subtitle'=>'Hero description','hero_button'=>'Primary action label','hero_button_url'=>'Primary action URL','featured_title'=>'Featured section title','featured_subtitle'=>'Featured section description'],
 'Footer' => ['footer_columns'=>'Footer columns','footer_about'=>'Brand description','copyright'=>'Copyright line'],
 'Social links' => ['social_facebook'=>'Facebook URL','social_instagram'=>'Instagram URL','social_linkedin'=>'LinkedIn URL','social_youtube'=>'YouTube URL']
];
?>
<section class="lm-page-hero"><div class="lm-container"><span class="lm-eyebrow">Administrator · Frontend</span><h1 class="lm-page-title">Site appearance</h1><p class="lm-page-subtitle">Manage your site's header, homepage, footer and theme. Changes become visible after saving.</p><div class="lm-hero-actions"><a class="lm-button lm-button-outline" href="/dashboard">← Dashboard</a><a class="lm-button lm-button-outline" href="/">Preview website ↗</a></div></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container lm-settings-wrap">
<?php if ($saved): ?><div class="lm-notice lm-notice-success" role="status">Frontend settings saved successfully.</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="lm-notice lm-notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/dashboard/appearance" class="lm-settings-form"><?= csrf_field() ?>
<?php foreach ($sections as $group => $fields): ?><fieldset class="lm-panel lm-settings-section"><legend><?= e($group) ?></legend><div class="lm-settings-grid">
<?php foreach ($fields as $key => $label): ?><div class="lm-setting-field"><label for="setting-<?= e($key) ?>"><?= e($label) ?></label>
<?php if (in_array($key, ['header_search','header_sticky'], true)): ?><select id="setting-<?= e($key) ?>" name="<?= e($key) ?>"><option value="1" <?= $settings[$key] === '1' ? 'selected' : '' ?>>Enabled</option><option value="0" <?= $settings[$key] === '0' ? 'selected' : '' ?>>Disabled</option></select>
<?php elseif ($key === 'default_theme'): ?><select id="setting-default_theme" name="default_theme"><?php foreach (['system','light','dark'] as $option): ?><option value="<?= $option ?>" <?= $settings[$key] === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select>
<?php elseif ($key === 'footer_columns'): ?><select id="setting-footer_columns" name="footer_columns"><?php foreach (['2','3','4'] as $count): ?><option value="<?= $count ?>" <?= $settings[$key] === $count ? 'selected' : '' ?>><?= $count ?> columns</option><?php endforeach; ?></select>
<?php elseif (in_array($key,['hero_subtitle','footer_about','featured_subtitle'],true)): ?><textarea id="setting-<?= e($key) ?>" name="<?= e($key) ?>" rows="3" maxlength="600"><?= e($settings[$key]) ?></textarea>
<?php else: ?><input id="setting-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($settings[$key]) ?>" maxlength="600" autocomplete="off"><?php endif; ?></div><?php endforeach; ?></div></fieldset><?php endforeach; ?>
<fieldset class="lm-panel lm-settings-section"><legend>Footer columns</legend><p class="lm-muted">Use one link per line: <code>Label|/path</code> or <code>Label|https://example.com</code>. Maximum eight links per column.</p><div class="lm-settings-grid">
<?php for ($i=1; $i<=4; $i++): ?><div class="lm-setting-field"><label for="footer_title_<?= $i ?>">Column <?= $i ?> title</label><input id="footer_title_<?= $i ?>" name="footer_title_<?= $i ?>" value="<?= e($settings['footer_title_'.$i]) ?>" maxlength="600"><label for="footer_links_<?= $i ?>">Column <?= $i ?> links</label><textarea id="footer_links_<?= $i ?>" name="footer_links_<?= $i ?>" rows="5" maxlength="1800"><?= e($settings['footer_links_'.$i]) ?></textarea></div><?php endfor; ?></div></fieldset>
<div class="lm-save-bar"><span>Changes are applied site-wide after saving.</span><button class="lm-button lm-button-primary" type="submit">Save appearance →</button></div>
</form></div></section>
