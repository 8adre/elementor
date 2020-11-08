<?php

namespace Elementor\Core\experiments;

use Elementor\Core\Base\Base_Object;
use Elementor\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Manager extends Base_Object {

	const STATUS_ALPHA = 1;

	const STATUS_BETA = 2;

	const STATE_DEFAULT = 0;

	const STATE_ACTIVE = 1;

	const STATE_INACTIVE = 2;

	private $features;

	/**
	 * @param array $options {
	 *     @type string $name
	 *     @type string $title
	 *     @type string $description
	 *     @type string $status
	 *     @type string $default
	 * }
	 *
	 * @return array|null
	 */
	public function add_feature( array $options ) {
		if ( isset( $this->features[ $options['name'] ] ) ) {
			return null;
		}

		$allowed_options = [ 'name', 'title', 'description', 'status', 'default' ];

		$this->features[ $options['name' ] ] = array_intersect_key( $options, array_flip( $allowed_options ) );

		$state = $this->get_saved_feature_state( $options['name' ] );

		if ( ! $state ) {
			$state = self::STATE_DEFAULT;
		}

		$this->features[ $options[ 'name' ] ]['state'] = $state;

		return $this->features[ $options['name' ] ];
	}

	public function get_features( $feature_name = null ) {
		return self::get_items( $this->features, $feature_name );
	}

	public function is_feature_active( $feature_name ) {
		$feature = $this->get_features( $feature_name );

		if ( ! $feature || self::STATE_INACTIVE === $feature['state'] ) {
			return false;
		}

		if ( self::STATE_DEFAULT === $feature['state'] ) {
			return self::STATE_ACTIVE === $feature['default'];
		}

		return true;
	}

	public function set_feature_default_state( $feature_name, $default_state ) {
		$feature = $this->get_features( $feature_name );

		if ( ! $feature ) {
			return;
		}

		$this->features[ $feature_name ]['default'] = $default_state;
	}

	private function get_feature_option_key( $feature_name ) {
		return 'elementor_experiment-' . $feature_name;
	}

	private function init_features() {
		$this->add_feature( [
			'name' => 'dom_optimization',
			'title' => __( 'Optimized DOM Output', 'elementor' ),
			'description' => __( 'Developers, Please Note! If you\'ve used custom code in Elementor, you might have experienced a snippet of code not running. Legacy DOM Output allows you to keep prior Elementor markup output settings, and have that lovely code running again.', 'elementor' )
				. ' <a href="https://go.elementor.com/wp-dash-legacy-optimized-dom" target="_blank">'
				. __( 'Learn More', 'elementor' ) . '</a>',
			'status' => self::STATUS_ALPHA,
			'default' => self::STATE_INACTIVE,
		] );

		do_action( 'elementor/experiments/features-registered' );
	}

	private function register_settings_fields( Tools $tools ) {
		$features = $this->get_features();

		$fields = [];

		foreach( $features as $feature_name => $feature ) {
			$feature_key = 'experiment-' . $feature_name;

			$fields[ $feature_key ]['label'] = $this->get_feature_settings_label_html( $feature );

			$fields[ $feature_key ]['field_args'] = $feature;

			$fields[ $feature_key ]['render'] = function( $feature ) {
				$this->render_feature_settings_field( $feature );
			};
		}

		$tools->add_tab(
			'experiments', [
				'label' => __( 'Experiments', 'elementor' ),
				'sections' => [
					'experiments' => [
						'callback' => function() {
							$this->render_settings_intro();
						},
						'fields' => $fields,
					],
					'usage' => $tools->get_usage_section(),
				],
			]
		);
	}

	private function render_settings_intro() {
		?>
		<h2><?php echo __( 'Elementor Experiments', 'elementor' ); ?></h2>
		<p class="e-experiments__description"><?php echo sprintf( __( 'The list items below are experiments Elementor conducts before they are released.
Please note that Experiments might change during their development. <a href="%s">Learn More</a>', 'elementor' ), 'https://elementor.com/help/share-usage-data/?utm_source=usage-data&utm_medium=wp-dash&utm_campaign=learn' ); ?></p>
		<?php
	}

	private function render_feature_settings_field( $feature ) {
		$states = [
			self::STATE_DEFAULT => __( 'Default', 'elementor' ),
			self::STATE_ACTIVE => __( 'Active', 'elementor' ),
			self::STATE_INACTIVE => __( 'Inactive', 'elementor' ),
		];

		$statuses = [
			self::STATUS_ALPHA => __( 'Alpha', 'elementor' ),
			self::STATUS_BETA => __( 'Beta', 'elementor' ),
		];
		?>
		<div class="e-experiment__content">
			<select id="e-experiment-<?php echo $feature['name']; ?>" class="e-experiment__select" name="<?php echo $this->get_feature_option_key( $feature['name'] ); ?>">
				<?php foreach( $states as $state_key => $state_title ) { ?>
					<option value="<?php echo $state_key; ?>" <?php selected( $state_key, $feature['state'] ); ?>><?php echo $state_title; ?></option>
				<?php } ?>
			</select>
			<div class="e-experiment__description"><?php echo $feature['description']; ?></div>
			<div class="e-experiment__status"><?php echo sprintf( __( 'Status: %s', 'elementor' ), $statuses[ $feature['status'] ] ); ?></div>
		</div>
		<?php
	}

	private function get_feature_settings_label_html( $feature ) {
		ob_start();

		$indicator_classes = 'e-experiment__title__indicator';

		if ( $this->is_feature_active( $feature['name'] ) ) {
			$indicator_classes .= ' e-experiment__title__indicator--active';
		}
		?>
		<div class="e-experiment__title">
			<div class="<?php echo $indicator_classes; ?>"></div>
			<label class="e-experiment__title__label" for="e-experiment-<?php echo $feature['name']; ?>"><?php echo $feature['title']; ?></label>
		</div>
		<?php

		return ob_get_clean();
	}

	private function get_saved_feature_state( $feature_name ) {
		return (int) get_option( $this->get_feature_option_key( $feature_name ) );
	}

	public function __construct() {
		$this->init_features();

		if ( is_admin() ) {
			$page_id = Tools::PAGE_ID;

			add_action( "elementor/admin/after_create_settings/{$page_id}", function( Tools $tools ) {
				$this->register_settings_fields( $tools );
			}, 11 );
		}
	}
}
