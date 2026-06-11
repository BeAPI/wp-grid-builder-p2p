<?php

namespace WP_Grid_Builder\P2P_Facet;

class Facet {
	use Singleton;

	public function init(): void {
		if ( ! function_exists( 'p2p_register_connection_type' ) ) {
			return;
		}

		add_filter( 'wp_grid_builder/custom_fields', [ $this, 'custom_fields' ], 11, 2 );
		add_filter( 'wp_grid_builder/indexer/index_object', [ $this, 'index' ], 10, 3 );

		add_action( 'p2p_created_connection', [ $this, 'p2p_created_connection' ] );
		add_action( 'p2p_delete_connections', [ $this, 'p2p_delete_connections' ] );
	}

	/**
	 * Index P2P connections
	 *
	 * @param array $rows Holds rows to index.
	 * @param int $object_id Object id to index.
	 * @param array $facet Holds facet settings.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 */
	public function index( $rows, $object_id, $facet ) {
		$source = explode( '/', $facet['source'] );

		if ( 'post_meta' !== reset( $source ) ) {
			return $rows;
		}

		$field = explode( '/p2p/', $facet['source'] );

		if ( ! empty( $field[1] ) ) {
			return $this->index_p2p( $rows, $object_id, $field[1] );
		}

		return $rows;
	}

	/**
	 * Index P2P connections
	 *
	 * @param array $rows Holds rows to index.
	 * @param int $object_id Object id to index.
	 * @param string $field_hierarchy
	 *
	 * @return array
	 * @since 1.0.0
	 * @access public
	 */
	public function index_p2p( array $rows, int $object_id, string $field_hierarchy ) {
		$source = explode( '/', $field_hierarchy );

		if ( count( $source ) < 2 ) {
			return $rows;
		}

		// Ensure P2P init hook was invoked.
		if ( ! did_action( 'p2p_init' ) ) {
			do_action( 'p2p_init' );
		}

		$connexion      = $source[0];
		$connexion_side = $source[1];
		$post_ptype     = get_post_type( $object_id );

		if ( $post_ptype !== $connexion_side ) {
			return $rows;
		}

		$p2p_query = new \WP_Query(
			[
				'post_type'       => $connexion_side,
				'connected_type'  => $connexion,
				'connected_items' => $object_id,
				'fields'          => 'ids',
				'no_found_rows'   => true,
				'posts_per_page'  => 500, //phpcs:ignore
			]
		);

		if ( ! $p2p_query->have_posts() ) {
			return $rows;
		}

		$new_parms = [];

		foreach ( $p2p_query->posts as $p2p_id ) {
			$new_parms[] = [
				'facet_value' => $p2p_id,
				'facet_name'  => get_the_title( $p2p_id ),
			];
		}

		$new_parms = apply_filters( 'wp_grid_builder_p2p_index_params', $new_parms, $connexion );

		return array_merge(
			$rows,
			$new_parms
		);
	}

	/**
	 * Retrieve all P2P fields
	 *
	 * @param array $fields Holds registered custom fields.
	 *
	 * @return array
	 * @since 1.0.0
	 * @access public
	 *
	 */
	public function custom_fields( $fields ) {
		$p2p_connexion = $this->get_p2p_connexion();

		if ( ! empty( $p2p_connexion ) ) {
			$fields['p2p'] = $p2p_connexion;
		}

		return $fields;
	}

	/**
	 * Get available P2P connexion.
	 *
	 * @return \P2P_Connection_Type[]
	 */
	protected function get_connexions(): array {
		$connexions = \P2P_Connection_Type_Factory::get_all_instances();

		/**
		 * Filters available P2P connexions.
		 *
		 * @param \P2P_Connection_Type[] $connexions List of P2P connexion.
		 *
		 * @since 1.0.0
		 *
		 */
		return apply_filters( 'wp_grid_builder_p2p_connexions', $connexions );
	}

	/**
	 * Get post type for a P2P_Side.
	 *
	 * Handle special case for P2P_Side_User.
	 *
	 * @param \P2P_Side $side
	 *
	 * @return string
	 */
	protected function get_post_type( \P2P_Side $side ): string {
		return ( is_a( $side, 'P2P_Side_User' ) ) ? 'user' : $side->first_post_type();
	}

	/**
	 * Prepare data for a P2P_Side.
	 *
	 * @param \P2P_Side $side
	 *
	 * @return object
	 */
	protected function parse_side( $side ): object {
		$data = [];

		$type = $this->get_post_type( $side );

		if ( 'user' === $type ) {
			$data['name']          = 'user';
			$data['singular_name'] = 'User';
		} else {
			$ptype                 = get_post_type_object( $type );
			$data['name']          = $ptype->name;
			$data['singular_name'] = $ptype->labels->singular_name;
		}

		return (object) $data;
	}

	/**
	 * @return array
	 */
	public function get_p2p_connexion(): array {
		static $load_connexions;

		if ( isset( $load_connexions ) ) {
			return $load_connexions;
		}

		$load_connexions = [];

		$connexions = $this->get_connexions();

		foreach ( $connexions as $connexion ) {

			$from_ptype = $this->parse_side( $connexion->side['from'] );
			$to_ptype   = $this->parse_side( $connexion->side['to'] );

			$from_title = ! empty( $connexion->labels['from']['singular_name'] )
				? esc_html( $connexion->labels['from']['singular_name'] )
				: esc_html( $from_ptype->singular_name );
			$to_title   = ! empty( $connexion->labels['to']['singular_name'] )
				? esc_html( $connexion->labels['to']['singular_name'] )
				: esc_html( $to_ptype->singular_name );

			$from_source_name = sprintf( '[%s → %s] %s', $from_title, $to_title, $from_title );

			/**
			 * Filters the source name for connexion's 'from' side.
			 *
			 * @param string $from_source_name Current source name.
			 * @param \P2P_Connection_Type $connexion Connexion object.
			 * @param string $from_title Title of the 'from' side
			 * @param object $from_ptype {
			 *
			 * @type string $name Post type's name for 'from' side.
			 * @type string $singular_name Post type's singular name for 'from' side.
			 * }
			 *
			 * @param string $to_title Title of the 'to' side
			 * @param object $to_ptype {
			 *
			 * @type string $name Post type's name for 'to' side.
			 * @type string $singular_name Post type's singular name for 'to' side.
			 * }
			 *
			 * @since 1.0.0
			 *
			 */
			$from_source_name = apply_filters( 'facetp2p_p2p_source_name_from', $from_source_name, $connexion, $from_title, $from_ptype, $to_title, $to_ptype );

			$to_source_name = sprintf( '[%s → %s] %s', $from_title, $to_title, $to_title );

			/**
			 * Filters the source name for connexion's 'to' side.
			 *
			 * @param string $to_source_name Current source name.
			 * @param \P2P_Connection_Type $connexion Connexion object.
			 * @param string $from_title Title of the 'from' side
			 * @param object $from_ptype {
			 *
			 * @type string $name Post type's name for 'from' side.
			 * @type string $singular_name Post type's singular name for 'from' side.
			 * }
			 *
			 * @param string $to_title Title of the 'to' side
			 * @param object $to_ptype {
			 *
			 * @type string $name Post type's name for 'to' side.
			 * @type string $singular_name Post type's singular name for 'to' side.
			 * }
			 *
			 * @since 1.0.0
			 *
			 */
			$to_source_name = apply_filters( 'facetp2p_p2p_source_name_to', $to_source_name, $connexion, $from_title, $from_ptype, $to_title, $to_ptype );

			$load_connexions[ sprintf( 'p2p/%s/%s', $connexion->name, $from_ptype->name ) ] = $from_source_name;
			$load_connexions[ sprintf( 'p2p/%s/%s', $connexion->name, $to_ptype->name ) ]   = $to_source_name;
		}

		return $load_connexions;
	}

	/**
	 * Handle P2P connection creation
	 *
	 * @param int $p2p_id Connection ID.
	 *
	 * @return void
	 * @since 1.0.0
	 * @access public
	 *
	 */
	public function p2p_created_connection( $p2p_id ): void {
		$connexion = p2p_get_connection( $p2p_id );

		if ( ! $connexion || ! isset( $connexion->p2p_from, $connexion->p2p_to ) ) {
			return;
		}

		$facets_groups = $this->get_facets_name_for_p2p_connection( $connexion );

		foreach ( $facets_groups as $direction => $facets ) {
			if ( 'from' === $direction && count( $facets ) > 0 ) {
				$this->index_object_id( (int) $connexion->p2p_from );
			}

			if ( 'to' === $direction && count( $facets ) > 0 ) {
				$this->index_object_id( (int) $connexion->p2p_to );
			}
		}
	}

	/**
	 * @param int|object $p2p_id $p2p_id
	 * @param string $direction
	 *
	 * @return array|\WP_Error
	 */
	protected function get_facets_name_for_p2p_connection( $p2p_id, $direction = '' ): \WP_Error|array {
		if ( '' !== $direction && ! in_array( $direction, [ 'from', 'to' ], true ) ) {
			return new \WP_Error(
				'wp_grid_builder_p2p_invalid_p2p_direction',
				sprintf( 'The direction %s is invalid. Allowed directions are "from" and "to".', $direction )
			);
		}

		$connexion      = ( isset( $p2p_id->p2p_type ) ) ? $p2p_id : p2p_get_connection( $p2p_id );
		$connexion_type = \P2P_Connection_Type_Factory::get_instance( $connexion->p2p_type );
		if ( ! $connexion_type ) {
			return new \WP_Error(
				'wp_grid_builder_p2p_invalid_connexion',
				sprintf( 'The connexion %s does not exist', $connexion->p2p_type )
			);
		}

		if ( '' !== $direction ) {
			return Helpers::get_facets_by_source_name(
				sprintf(
					'p2p/%s/%s',
					$connexion->p2p_type,
					$this->get_post_type( $connexion_type->side[ $direction ] )
				)
			);
		}

		return [
			'from' => Helpers::get_facets_by_source_name(
				sprintf(
					'p2p/%s/%s',
					$connexion->p2p_type,
					$this->get_post_type( $connexion_type->side['from'] )
				)
			),
			'to'   => Helpers::get_facets_by_source_name(
				sprintf(
					'p2p/%s/%s',
					$connexion->p2p_type,
					$this->get_post_type( $connexion_type->side['to'] )
				)
			),
		];
	}

	/**
	 * Index object ID using WP Grid Builder Indexer
	 *
	 * Uses the same method as WP Grid Builder's Indexer class.
	 * Follows the same pattern as save_post() in class- indexer . php( line 134).
	 * Uses the same instantiation pattern as class- indexer . php route( line 86).
	 *
	 * @param int $object_id Object ID to index .
	 * @param string $type Object type( post / user / term ) . default 'post' .
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function index_object_id( $object_id, $type = 'post' ): void {
		if ( empty( $object_id ) ) {
			return;
		}

		// Use WP Grid Builder Indexer class, same pattern as in includes/routes/class-indexer.php.
		if ( ! class_exists( '\WP_Grid_Builder\Includes\Indexer' ) ) {
			return;
		}

		( new \WP_Grid_Builder\Includes\Indexer() )->index_object_id( $object_id, $type );
	}

	/**
	 * Handle P2P connection deletion
	 *
	 * @param array $p2p_ids Array of connection IDs.
	 *
	 * @return void
	 * @since 1.0.0
	 * @access public
	 *
	 */
	public function p2p_delete_connections( $p2p_ids ): void {
		if ( empty( $p2p_ids ) || ! is_array( $p2p_ids ) ) {
			return;
		}

		/* @var \wpdb $wpdb */
		global $wpdb;

		foreach ( $p2p_ids as $p2p_id ) {
			$connexion            = p2p_get_connection( $p2p_id );
			$facets_by_directions = $this->get_facets_name_for_p2p_connection( $connexion );

			if ( is_wp_error( $facets_by_directions ) || empty( $facets_by_directions ) ) {
				continue;
			}

			foreach ( $facets_by_directions as $direction => $facets ) {

				$names = wp_list_pluck( $facets, 'slug' );
				$names = array_filter( array_map( 'esc_sql', $names ) );
				if ( empty( $names ) ) {
					continue;
				}

				$facet_name_in = "'"; //phpcs:ignore Generic.Formatting.MultipleStatementAlignment.NotSameWarning
				$facet_name_in .= implode( "', '", $names );
				$facet_name_in .= "'";

				//phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query(
					$wpdb->prepare(
						"
					DELETE FROM {$wpdb->prefix}wpgb_index
					WHERE object_id IN (%d, %d)
					AND facet_value IN (%d, %d)
					AND slug IN ($facet_name_in)
					",
						$connexion->p2p_from,
						$connexion->p2p_to,
						$connexion->p2p_from,
						$connexion->p2p_to,
					)
				);
				//phpcs:enable
			}
		}
	}
}
