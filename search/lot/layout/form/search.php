<?php $to = $url->current(false, false); ?>
<form action="<?= $site->is('page') ? dirname($to) : $to; ?>" class="form-search" id="search" method="get" name="search">
  <p>
    <input class="input" name="<?= $key = $state->x->search->key ?? 0; ?>" type="text" value="<?= 0 !== $key ? get($_GET, $key) : ""; ?>">
    <button class="button" type="submit">
      <?= i('Search'); ?>
    </button>
  </p>
</form>