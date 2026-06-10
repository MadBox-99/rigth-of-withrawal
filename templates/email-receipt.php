<?php
/** @var array $data */
?>
<h2><?php echo esc_html__('Átvételi elismervény – Elállás a szerződéstől', 'elallasi-funkcio'); ?></h2>
<p><?php echo esc_html(sprintf(__('Tisztelt %s!', 'elallasi-funkcio'), $data['consumer_name'] ?? '')); ?></p>
<p><?php echo esc_html__('Visszaigazoljuk, hogy elállási nyilatkozatát átvettük.', 'elallasi-funkcio'); ?></p>
<ul>
  <li><strong><?php echo esc_html__('Rendelés/szerződés azonosító:', 'elallasi-funkcio'); ?></strong> <?php echo esc_html($data['order_reference'] ?? ''); ?></li>
  <li><strong><?php echo esc_html__('Elállás lényege:', 'elallasi-funkcio'); ?></strong> <?php echo esc_html($data['intent_text'] ?? ''); ?></li>
  <li><strong><?php echo esc_html__('Dátum és időpont:', 'elallasi-funkcio'); ?></strong> <?php echo esc_html($data['received_at'] ?? ''); ?></li>
</ul>
