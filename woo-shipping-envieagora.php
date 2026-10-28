<?php
/**
 * Plugin Name: WooCommerce ENVIE AGORA Shipping
 * Plugin URI : https://github.com/envieagora/woo-shipping-envieagora
 * Description: Integração de cotação de frete da ENVIE AGORA.
 * Version: 1.0.1
 * Author: Equipe ENVIE AGORA
 * Author URI: https://envieagora.com.br
 * Requires PHP: 7.4
 */

// Segurança: Impede acesso direto ao arquivo
if (! defined( 'ABSPATH' )){
    exit;
}
/**
 * Inicializa o método de entrega após o WooCommerce carregar
 */

add_action('woocommerce_shipping_init', 'envie_agora_init_shipping_method');

function envie_agora_init_shipping_method() {
    if ( ! class_exists( 'WC_Envie_Agora_Shipping' ) ) {
        // Aqui chamamos o arquivo com a lógica da classe que você vai desenvolver
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-shipping-envie-agora.php';
    }
}

/**
 * Adiciona o método da ENVIE AGORA á lista de métodos do WooCommerce
 */
add_filter('woocommerce_shipping_methods', 'envie_agora_add_method');

function envie_agora_add_method( $methods) {
    $methods['envie_agora_shipping'] = 'WC_Envie_Agora_Shipping';
    return $methods;
}
