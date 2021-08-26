<?php $to = $url->clean; ?>
<form action="<?= $site->is('page') ? dirname($to) : $to; ?>" class="form-search" id="search" method="get" name="search">
  <p>
    <input class="input" name="<?= $key = $state->x->search->key ?? 0; ?>" type="text" value="<?= 0 !== $key ? Get::get($key) : ""; ?>">
    <button class="button" type="submit"><?= i('Search'); ?></button>
  </p>
</form>