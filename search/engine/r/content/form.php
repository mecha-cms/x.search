<?php $to = $url->clean; ?>
<form action="<?php echo $site->is('page') ? dirname($to) : $to; ?>" class="form-search" method="get" name="search">
  <p>
    <input class="input" name="<?php echo state('search')['key']; ?>" type="text">
    <button class="button" type="submit"><?php echo $language->doSearch; ?></button>
  </p>
</form>