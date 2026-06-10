<?php /** @var array $errors */ $errors = $errors ?? []; ?>
<form class="elallas-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
  <h2 class="elallas-title"><?php echo esc_html__('Elállás a szerződéstől', 'elallasi-funkcio'); ?></h2>
  <input type="hidden" name="action" value="elallas_prepare">
  <?php echo wp_nonce_field('elallas_prepare', '_wpnonce', true, false); ?>

  <p>
    <label for="elallas_name"><?php echo esc_html__('Név', 'elallasi-funkcio'); ?></label>
    <input id="elallas_name" type="text" name="consumer_name" required>
    <?php if (isset($errors['consumer_name'])): ?><span class="elallas-error"><?php echo esc_html($errors['consumer_name']); ?></span><?php endif; ?>
  </p>
  <p>
    <label for="elallas_email"><?php echo esc_html__('E-mail (visszaigazoláshoz)', 'elallasi-funkcio'); ?></label>
    <input id="elallas_email" type="email" name="contact_email" required>
    <?php if (isset($errors['contact_email'])): ?><span class="elallas-error"><?php echo esc_html($errors['contact_email']); ?></span><?php endif; ?>
  </p>
  <p>
    <label for="elallas_ref"><?php echo esc_html__('Rendelés/szerződés azonosító', 'elallasi-funkcio'); ?></label>
    <input id="elallas_ref" type="text" name="order_reference" required>
    <?php if (isset($errors['order_reference'])): ?><span class="elallas-error"><?php echo esc_html($errors['order_reference']); ?></span><?php endif; ?>
  </p>
  <p>
    <label for="elallas_intent"><?php echo esc_html__('Elállási szándék', 'elallasi-funkcio'); ?></label>
    <textarea id="elallas_intent" name="intent_text" required></textarea>
    <?php if (isset($errors['intent_text'])): ?><span class="elallas-error"><?php echo esc_html($errors['intent_text']); ?></span><?php endif; ?>
  </p>
  <p><button type="submit"><?php echo esc_html__('Tovább', 'elallasi-funkcio'); ?></button></p>
</form>
