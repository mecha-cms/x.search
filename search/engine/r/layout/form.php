<?php $to = $url->clean; ?>
<form action="<?= $site->is('page') ? dirname($to) : $to; ?>" class="form-search" method="get" name="search">
  <p>
    <input class="input" name="<?= State::get('x.search.key'); ?>" type="text">
    <button class="button" type="submit"><?= i('Search'); ?></button>
  </p>
</form>