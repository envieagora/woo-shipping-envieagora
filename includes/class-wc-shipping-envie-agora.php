<?php
/**
 * Lógica de cotação de frete - ENVIE AGORA (Integração API v1)
 * 
 */

if (!defined('ABSPATH'))
    exit;

class WC_Envie_Agora_Shipping extends WC_Shipping_Method
{

    protected $api_token;
    protected $zip_origin;
    protected $standard_measures;
    protected $height;
    protected $width;
    protected $length;

    public function __construct($instance_id = 0)
    {
        $this->instance_id = absint($instance_id);
        $this->id = 'envie_agora_shipping';
        $this->method_title = 'ENVIE AGORA';
        $this->method_description = 'Cálculo de frete em tempo real do ENVIE AGORA';
        $this->supports = array('shipping-zones', 'instance-settings');

        $this->init();
    }

    public function init()
    {
        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->api_token = $this->get_option('api_token');
        $this->standard_measures = $this->get_option('standard_measures');
        $this->height = $this->get_option('height');
        $this->width = $this->get_option('width');
        $this->length = $this->get_option('length');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->instance_form_fields = array(
            'enabled' => array(
                'title' => 'Ativar',
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Título do Método',
                'type' => 'text',
                'description' => 'Nome exibido no painel e no checkout.',
                'default' => 'ENVIE AGORA',
                'desc_tip' => true
            ),
            'api_token' => array(
                'title' => 'Token da API',
                'type' => 'text',
                'description' => 'Cole aqui o Token.',
                'default' => ''
            ),
            'zip_origin' => array(
                'title' => 'CEP de Origem',
                'type' => 'text',
                'default' => ''
            ),
            'standard_measures' => [
                'title' => 'Medidas padrões',
                'type' => 'checkbox',
                'description' => 'Utilize medidas padrões ao invés das medidas configuradas nos produtos',
                'default' => 'no'
            ],
            'height' => [
                'title' => 'Altura padrão (cm)',
                'type' => 'text',
                'description' => 'Não utilize ponto ou virgula (ex: 1)'
            ],
            'width' => [
                'title' => 'Largura padrão (cm)',
                'type' => 'text',
                'description' => 'Não utilize ponto ou virgula (ex: 8)'
            ],
            'length' => [
                'title' => 'Comprimento padrão (cm)',
                'type' => 'text',
                'description' => 'Não utilize ponto ou virgula (ex: 13)'
            ]
        );

        $this->form_fields = $this->instance_form_fields;
    }

    public function calculate_shipping($package = array()): void
    {
        $origin_zip = preg_replace('/\D/', '', $this->get_option('zip_origin'));
        $dest_zip = preg_replace('/\D/', '', $package['destination']['postcode']);

        if ($this->standard_measures) {
            $max_l = $this->length;
            $max_h = $this->height;
            $max_w = $this->width;
        } else {
            // Dimensões Dinâmicas baseadas nos itens do carrinho
            $items = WC()->cart->get_cart();
            $max_l = 13;
            $max_h = 1;
            $max_w = 8;

            foreach ($items as $item) {
                $product = $item['data'];
                $p_l = (float) $product->get_length();
                $p_h = (float) $product->get_height();
                $p_w = (float) $product->get_width();

                if ($p_l > $max_l)
                    $max_l = $p_l;
                if ($p_h > $max_h)
                    $max_h = $p_h;
                if ($p_w > $max_w)
                    $max_w = $p_w;
            }
        }

        $weight = (float) WC()->cart->get_cart_contents_weight();
		
		if (get_option('woocommerce_weight_unit', 'kg') === "g") {
			$weight /= 1000;
		}


        $body = array(
            'ceporigem' => $origin_zip,
            'cepdestino' => $dest_zip,
            'peso' => $weight,
            'comprimento' => $max_l,
            'altura' => $max_h,
            'largura' => $max_w,
            'valordeclarado' => (float) WC()->cart->get_subtotal(),
            'avisorecebimento' => 'N',
            'maopropria' => 'N',
        );



        $response = wp_remote_post('https://envieagora.com.br/api/v1/precosprazos', array(
            'method' => 'POST',
            'timeout' => 30,
            'sslverify' => true,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'apitoken' => trim($this->api_token)
            ),
            'body' => json_encode($body),
        ));

        if (is_wp_error($response)) {
            error_log($response->get_error_message());
            return;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        error_log(wp_remote_retrieve_body($response));
        error_log(json_encode($body));

        if (isset($result['data']['precosprazos']) && is_array($result['data']['precosprazos'])) {
            foreach ($result['data']['precosprazos'] as $opcao) {
                $this->add_rate(array(
                    'id' => $this->id . '_' . $opcao['codigoServico'],
                    'label' => $opcao['nomeServico'] . " (via ENVIE AGORA)",
                    'cost' => (float) $opcao['valor'],
                    'package' => $package,
                ));
            }
        }
    }
}