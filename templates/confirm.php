<?php /** @var array $data */ /** @var int $id */ /** @var string $token */ ?>
<form class="elallas-confirm" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
  <h2><?php echo esc_html__('Elállás véglegesítése', 'elallasi-funkcio'); ?></h2>
  <input type="hidden" name="action" value="elallas_confirm">
  <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
  <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
  <?php echo wp_nonce_field('elallas_confirm', '_wpnonce', true, false); ?>

  <p><?php echo esc_html__('Kérjük, erősítse meg, hogy véglegesen el kíván állni a szerződéstől.', 'elallasi-funkcio'); ?></p>
  <ul>
    <li><?php echo esc_html__('Név:', 'elallasi-funkcio'); ?> <?php echo esc_html($data['consumer_name'] ?? ''); ?></li>
    <li><?php echo esc_html__('Azonosító:', 'elallasi-funkcio'); ?> <?php echo esc_html($data['order_reference'] ?? ''); ?></li>
  </ul>
  <p><button type="submit" class="elallas-finalize"><?php echo esc_html__('Elállás véglegesítése', 'elallasi-funkcio'); ?></button></p>
</form>
