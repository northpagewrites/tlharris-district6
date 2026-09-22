<?php
/**
 * Plugin Name:       T. L. Harris Core
 * Description:       Portable content architecture, structured public-record metadata, and configurable site identity for the T. L. Harris public-service platform.
 * Version:           0.3.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Text Domain:       tlharris-core
 * License:           GPL-2.0-or-later
 *
 * Content lives here, not in the theme, so it survives a theme change.
 * The current public role is configuration, not brand: see the identity
 * settings below and the `tlharris/identity` block binding.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TLHARRIS_CORE_VERSION', '0.3.0' );

/* -------------------------------------------------------------------------
 * Site identity
 * ---------------------------------------------------------------------- */

/**
 * The identity field table. Everything else derives from this.
 *
 * @return array<string,array<string,mixed>>
 */
function tlharris_identity_fields() {
	return array(
		'display_name'         => array(
			'label'   => 'Display name',
			'type'    => 'text',
			'default' => 'T. L. Harris',
			'help'    => 'Name-first identity.',
		),
		'role_title'           => array(
			'label'   => 'Role title',
			'type'    => 'text',
			'default' => 'Board Member',
			'help'    => 'Current public role. Content, not brand.',
		),
		'office_name'          => array(
			'label'   => 'Office / institution',
			'type'    => 'text',
			'default' => 'Memphis-Shelby County Schools',
		),
		'district'             => array(
			'label'   => 'District',
			'type'    => 'text',
			'default' => 'District 6',
		),
		'tagline'              => array(
			'label'   => 'Tagline',
			'type'    => 'text',
			'default' => 'Raising the Standard',
		),
		'role_source_url'      => array(
			'label'   => 'Role source URL',
			'type'    => 'url',
			'default' => 'https://www.scsk12.org/board/?M=6&MID=27&PN=44',
			'help'    => 'Official source that confirms the role above.',
		),
		'role_last_verified'   => array(
			'label'   => 'Role last verified',
			'type'    => 'date',
			'default' => '',
			'help'    => 'Re-check the source before publishing date-sensitive content.',
		),
		'public_email'         => array(
			'label'   => 'Public contact email',
			'type'    => 'email',
			'default' => '',
			'help'    => 'Leave empty until an approved domain-based address exists.',
		),
		'mailing_address'      => array(
			'label'   => 'Public mailing address',
			'type'    => 'textarea',
			'default' => '',
		),
		'board_home_url'       => array(
			'label'   => 'MSCS Board home URL',
			'type'    => 'url',
			'default' => 'https://www.scsk12.org/board/',
		),
		'board_office_url'     => array(
			'label'   => 'MSCS Board Office URL',
			'type'    => 'url',
			'default' => 'https://www.scsk12.org/board/?PN=45',
		),
		'board_docs_url'       => array(
			'label'   => 'BoardDocs URL',
			'type'    => 'url',
			'default' => 'https://www.boarddocs.com/tn/scsk12/Board.nsf/Public',
		),
		'board_office_phone'   => array(
			'label'   => 'MSCS Board Office phone',
			'type'    => 'text',
			'default' => '(901) 416-5447',
		),
		'board_office_email'   => array(
			'label'   => 'MSCS Board Office email',
			'type'    => 'email',
			'default' => 'boardoffice@scsk12.org',
		),
		'site_operator'        => array(
			'label'   => 'Site operator',
			'type'    => 'text',
			'default' => '',
			'help'    => 'Who publishes this site. Used in the footer and legal pages.',
		),
		'official_site_notice' => array(
			'label'   => 'Official-site notice',
			'type'    => 'textarea',
			'default' => 'This website is published by the office of T. L. Harris and is not an official Memphis-Shelby County Schools website.',
		),
		'primary_domain'       => array(
			'label'   => 'Primary domain',
			'type'    => 'url',
			'default' => '',
		),
	);
}

/**
 * @return array<string,string>
 */
function tlharris_identity_defaults() {
	$defaults = array();
	foreach ( tlharris_identity_fields() as $key => $field ) {
		$defaults[ $key ] = (string) ( $field['default'] ?? '' );
	}
	return $defaults;
}

/**
 * @param string|null $key Single field, or null for all of them.
 * @return array<string,string>|string
 */
function tlharris_get_identity( $key = null ) {
	$stored   = get_option( 'tlharris_identity', array() );
	$identity = wp_parse_args( is_array( $stored ) ? $stored : array(), tlharris_identity_defaults() );

	if ( null === $key ) {
		return $identity;
	}

	return isset( $identity[ $key ] ) ? (string) $identity[ $key ] : '';
}

/**
 * "Board Member · District 6" — assembled, never hardcoded in a template.
 */
function tlharris_identity_line() {
	$role     = trim( tlharris_get_identity( 'role_title' ) );
	$district = trim( tlharris_get_identity( 'district' ) );

	if ( '' === $role ) {
		return $district;
	}

	return '' === $district ? $role : $role . ' · ' . $district;
}

function tlharris_sanitize_date( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
	return ( $date && $date->format( 'Y-m-d' ) === $value ) ? $value : '';
}

function tlharris_sanitize_identity_value( $value, $type ) {
	$value = is_scalar( $value ) ? (string) $value : '';

	switch ( $type ) {
		case 'email':
			return sanitize_email( $value );
		case 'url':
			return esc_url_raw( trim( $value ) );
		case 'date':
			return tlharris_sanitize_date( $value );
		case 'textarea':
			return sanitize_textarea_field( $value );
		default:
			return sanitize_text_field( $value );
	}
}

/**
 * Keys absent from the payload keep their STORED value. Falling back to the
 * shipped defaults would silently revert role and district on any partial
 * write (WP-CLI, REST, import).
 */
function tlharris_sanitize_identity( $input ) {
	$fields  = tlharris_identity_fields();
	$current = tlharris_get_identity();
	$output  = array();

	foreach ( $fields as $key => $field ) {
		if ( ! is_array( $input ) || ! array_key_exists( $key, $input ) ) {
			$output[ $key ] = $current[ $key ];
			continue;
		}

		$output[ $key ] = tlharris_sanitize_identity_value( $input[ $key ], $field['type'] );
	}

	return $output;
}

/**
 * Registered on `init`, not `admin_init`, so REST and the front end see it.
 * Typed `object` with a property schema: an `array` type without
 * `show_in_rest.schema.items` trips core's _doing_it_wrong().
 */
function tlharris_register_identity_setting() {
	$properties = array();
	foreach ( tlharris_identity_fields() as $key => $field ) {
		$properties[ $key ] = array(
			'type'        => 'string',
			'description' => $field['label'],
		);
	}

	register_setting(
		'tlharris_identity_group',
		'tlharris_identity',
		array(
			'type'              => 'object',
			'description'       => 'Public identity for the T. L. Harris public-service site.',
			'sanitize_callback' => 'tlharris_sanitize_identity',
			'default'           => tlharris_identity_defaults(),
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => $properties,
					'additionalProperties' => false,
				),
			),
		)
	);
}
add_action( 'init', 'tlharris_register_identity_setting' );

/**
 * Block binding so templates read identity instead of hardcoding it.
 *
 * Usage in a template:
 * <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"tlharris/identity","args":{"key":"line"}}}}} -->
 */
function tlharris_register_identity_binding() {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}

	register_block_bindings_source(
		'tlharris/identity',
		array(
			'label'              => 'T. L. Harris identity',
			'get_value_callback' => 'tlharris_identity_binding_value',
		)
	);
}
add_action( 'init', 'tlharris_register_identity_binding' );

/**
 * @param array $source_args Expects a `key`.
 * @return string
 */
function tlharris_identity_binding_value( $source_args ) {
	$key = is_array( $source_args ) && ! empty( $source_args['key'] ) ? (string) $source_args['key'] : 'line';

	if ( 'line' === $key ) {
		return tlharris_identity_line();
	}

	if ( 'name_and_line' === $key ) {
		return trim( tlharris_get_identity( 'display_name' ) . ' — ' . tlharris_identity_line() );
	}

	if ( 'office_and_district' === $key ) {
		$office   = trim( tlharris_get_identity( 'office_name' ) );
		$district = trim( tlharris_get_identity( 'district' ) );
		return trim( $office . ( $district ? ' · ' . $district : '' ) );
	}

	return tlharris_get_identity( $key );
}

/**
 * Kept so existing content using the shortcode does not break. New templates
 * should use the block binding above.
 */
function tlharris_identity_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'part' => 'line' ), $atts, 'tlharris_identity' );

	$map = array(
		'name'     => 'display_name',
		'office'   => 'office_name',
		'district' => 'district',
		'tagline'  => 'tagline',
		'role'     => 'role_title',
	);

	$key = $map[ $atts['part'] ] ?? $atts['part'];

	return esc_html( tlharris_identity_binding_value( array( 'key' => $key ) ) );
}
add_shortcode( 'tlharris_identity', 'tlharris_identity_shortcode' );

/* -------------------------------------------------------------------------
 * Identity settings screen
 * ---------------------------------------------------------------------- */

function tlharris_add_identity_menu() {
	add_options_page(
		'T. L. Harris Site Identity',
		'T. L. Harris Identity',
		'manage_options',
		'tlharris-identity',
		'tlharris_render_identity_page'
	);
}
add_action( 'admin_menu', 'tlharris_add_identity_menu' );

function tlharris_render_identity_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$identity = tlharris_get_identity();
	$fields   = tlharris_identity_fields();
	?>
	<div class="wrap">
		<h1>T. L. Harris Site Identity</h1>
		<p>These values are configuration, not brand. The public role can change without rebuilding the theme.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'tlharris_identity_group' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $field ) : ?>
					<?php
					$id    = 'tlharris-' . $key;
					$name  = 'tlharris_identity[' . $key . ']';
					$value = $identity[ $key ] ?? '';
					?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						</th>
						<td>
							<?php if ( 'textarea' === $field['type'] ) : ?>
								<textarea class="large-text" rows="3" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
							<?php else : ?>
								<?php
								$input_type = 'text';
								if ( 'email' === $field['type'] ) {
									$input_type = 'email';
								} elseif ( 'url' === $field['type'] ) {
									$input_type = 'url';
								} elseif ( 'date' === $field['type'] ) {
									$input_type = 'date';
								}
								?>
								<input class="regular-text" type="<?php echo esc_attr( $input_type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
							<?php endif; ?>
							<?php if ( ! empty( $field['help'] ) ) : ?>
								<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( 'Save Identity Settings' ); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Content architecture
 * ---------------------------------------------------------------------- */

/**
 * `has_archive` is false everywhere on purpose. Curated page templates own the
 * listings; a CPT archive registers its rewrite rule at 'top' priority and
 * would shadow the same-slug Page.
 *
 * @return array<string,array<string,mixed>>
 */
function tlharris_post_types() {
	return array(
		'board_update'    => array(
			'plural'   => 'Board Updates',
			'singular' => 'Board Update',
			'slug'     => 'board-updates',
			'icon'     => 'dashicons-megaphone',
		),
		'board_action'    => array(
			'plural'   => 'Board Actions',
			'singular' => 'Board Action',
			'slug'     => 'board-actions',
			'icon'     => 'dashicons-yes-alt',
		),
		'board_document'  => array(
			'plural'   => 'Board Documents',
			'singular' => 'Board Document',
			'slug'     => 'board-documents',
			'icon'     => 'dashicons-media-document',
		),
		'committee'       => array(
			'plural'   => 'Committees',
			'singular' => 'Committee',
			'slug'     => 'committees',
			'icon'     => 'dashicons-groups',
		),
		'priority'        => array(
			'plural'   => 'Priorities & Progress',
			'singular' => 'Priority',
			'slug'     => 'priorities-progress',
			'icon'     => 'dashicons-chart-line',
		),
		'event'           => array(
			'plural'   => 'Events',
			'singular' => 'Event',
			'slug'     => 'events',
			'icon'     => 'dashicons-calendar-alt',
		),
		'district_school' => array(
			'plural'   => 'District 6 Schools',
			'singular' => 'District 6 School',
			'slug'     => 'district-6-schools',
			'icon'     => 'dashicons-welcome-learn-more',
		),
		'press_item'      => array(
			'plural'   => 'Press & Media',
			'singular' => 'Press Item',
			'slug'     => 'press-items',
			'icon'     => 'dashicons-format-aside',
		),
	);
}

function tlharris_register_post_types() {
	foreach ( tlharris_post_types() as $slug => $type ) {
		$plural   = $type['plural'];
		$singular = $type['singular'];

		register_post_type(
			$slug,
			array(
				'labels'        => array(
					'name'                  => $plural,
					'singular_name'         => $singular,
					'menu_name'             => $plural,
					'add_new'               => 'Add ' . $singular,
					'add_new_item'          => 'Add ' . $singular,
					'edit_item'             => 'Edit ' . $singular,
					'new_item'              => 'New ' . $singular,
					'view_item'             => 'View ' . $singular,
					'view_items'            => 'View ' . $plural,
					'search_items'          => 'Search ' . $plural,
					'not_found'             => 'No ' . strtolower( $plural ) . ' yet.',
					'not_found_in_trash'    => 'No ' . strtolower( $plural ) . ' in Trash.',
					'all_items'             => $plural,
					'archives'              => $singular . ' Archives',
					'item_published'        => $singular . ' published.',
					'item_updated'          => $singular . ' updated.',
				),
				'public'        => true,
				'show_in_rest'  => true,
				'has_archive'   => false,
				'show_in_menu'  => 'tlharris-public-record',
				'menu_icon'     => $type['icon'],
				'rewrite'       => array(
					'slug'       => $type['slug'],
					'with_front' => false,
				),
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes' ),
			)
		);
	}
}
add_action( 'init', 'tlharris_register_post_types' );

/**
 * One top-level menu so eight content types do not sprawl down the sidebar.
 */
function tlharris_register_public_record_menu() {
	add_menu_page(
		'Public Record',
		'Public Record',
		'edit_posts',
		'tlharris-public-record',
		'tlharris_render_public_record_page',
		'dashicons-portfolio',
		21
	);
}
add_action( 'admin_menu', 'tlharris_register_public_record_menu' );

function tlharris_render_public_record_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	echo '<div class="wrap"><h1>Public Record</h1><p>Every entry needs an official source and a verification date before it is published.</p><ul>';
	foreach ( tlharris_post_types() as $slug => $type ) {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( admin_url( 'edit.php?post_type=' . $slug ) ),
			esc_html( $type['plural'] )
		);
	}
	echo '</ul></div>';
}

function tlharris_register_taxonomies() {
	register_taxonomy(
		'progress_status',
		array( 'priority' ),
		array(
			'labels'       => array(
				'name'          => 'Progress Status',
				'singular_name' => 'Progress Status',
			),
			'public'       => true,
			'show_in_rest' => true,
			'hierarchical' => true,
			'rewrite'      => array( 'slug' => 'progress-status' ),
		)
	);

	register_taxonomy(
		'priority_category',
		array( 'priority' ),
		array(
			'labels'       => array(
				'name'          => 'Priority Categories',
				'singular_name' => 'Priority Category',
			),
			'public'       => true,
			'show_in_rest' => true,
			'hierarchical' => true,
			'rewrite'      => array( 'slug' => 'priority-category' ),
		)
	);

	register_taxonomy(
		'update_type',
		array( 'board_update' ),
		array(
			'labels'       => array(
				'name'          => 'Update Types',
				'singular_name' => 'Update Type',
			),
			'public'       => true,
			'show_in_rest' => true,
			'hierarchical' => true,
			'rewrite'      => array( 'slug' => 'update-type' ),
		)
	);

	register_taxonomy(
		'school_level',
		array( 'district_school' ),
		array(
			'labels'       => array(
				'name'          => 'School Levels',
				'singular_name' => 'School Level',
			),
			'public'       => true,
			'show_in_rest' => true,
			'hierarchical' => true,
			'rewrite'      => array( 'slug' => 'school-level' ),
		)
	);

	// Shared across record types: a topic term is what links a priority to the
	// Board work, documents and schools that bear on it.
	register_taxonomy(
		'board_topic',
		array( 'board_update', 'board_action', 'board_document', 'priority', 'district_school' ),
		array(
			'labels'       => array(
				'name'          => 'Board Topics',
				'singular_name' => 'Board Topic',
			),
			'public'       => true,
			'show_in_rest' => true,
			'hierarchical' => true,
			'rewrite'      => array( 'slug' => 'board-topic' ),
		)
	);
}
add_action( 'init', 'tlharris_register_taxonomies' );

/**
 * The only permitted status values, in the order a record actually moves
 * through them. No "Success", "Win" or "On Track": a tracker that grades
 * itself in celebratory language is a campaign page, not a public record.
 * Reused wherever statuses are seeded, listed or counted, so the tracker
 * never presents them in an arbitrary (e.g. alphabetical) order that reads
 * like a ranking.
 *
 * @return string[]
 */
function tlharris_progress_status_labels() {
	return array( 'Not Started', 'Monitoring', 'In Progress', 'Completed', 'On Hold' );
}

/**
 * Fixed vocabularies. A free-form status column is not an accountability system.
 */
function tlharris_seed_terms() {
	$terms = array(
		'progress_status'   => tlharris_progress_status_labels(),
		'update_type'       => array( 'Board Update', 'Report to District 6', 'Statement', 'Community', 'Education' ),
		'school_level'      => array( 'Elementary', 'Middle', 'High' ),
		'priority_category' => array(
			'Student Achievement',
			'Early Literacy',
			'Safe & Supportive Schools',
			'Accountability & Stewardship',
			'Family & Community Engagement',
		),
	);

	foreach ( $terms as $taxonomy => $names ) {
		foreach ( $names as $name ) {
			if ( ! term_exists( $name, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy );
			}
		}
	}
}

/* -------------------------------------------------------------------------
 * Structured metadata
 *
 * Keys are deliberately NOT underscore-prefixed. Core's post-meta block
 * binding calls is_protected_meta() and returns null for `_`-prefixed keys,
 * so protected meta can never be displayed in a template.
 * ---------------------------------------------------------------------- */

/**
 * @return array<string,array<string,array<string,mixed>>>
 */
function tlharris_meta_fields() {
	$source = array(
		'tlharris_source_url'   => array( 'label' => 'Official source URL', 'type' => 'url' ),
		'tlharris_last_verified' => array( 'label' => 'Last verified', 'type' => 'date' ),
	);

	return array(
		'priority'        => array(
			'tlharris_commitment'        => array( 'label' => 'Commitment / goal', 'type' => 'textarea' ),
			'tlharris_why_it_matters'    => array( 'label' => 'Why it matters', 'type' => 'textarea' ),
			'tlharris_baseline'          => array( 'label' => 'Baseline', 'type' => 'textarea' ),
			'tlharris_target'            => array( 'label' => 'Target', 'type' => 'textarea' ),
			'tlharris_target_date'       => array( 'label' => 'Target date', 'type' => 'date' ),
			'tlharris_role'              => array( 'label' => "Harris's role", 'type' => 'textarea' ),
			'tlharris_direct_control'    => array( 'label' => 'What he directly controls', 'type' => 'textarea' ),
			'tlharris_influence'         => array( 'label' => 'What he can influence', 'type' => 'textarea' ),
			'tlharris_district_context'  => array( 'label' => 'District / school context', 'type' => 'textarea' ),
			'tlharris_action_taken'      => array( 'label' => 'Actions taken', 'type' => 'textarea' ),
			'tlharris_evidence_note'     => array( 'label' => 'Evidence', 'type' => 'textarea' ),
			'tlharris_evidence_url'      => array( 'label' => 'Evidence URL', 'type' => 'url' ),
			'tlharris_what_next'         => array( 'label' => 'What happens next', 'type' => 'textarea' ),
			'tlharris_next_update'       => array( 'label' => 'Next update due', 'type' => 'date' ),
			'tlharris_campaign_origin'   => array(
				'label'   => 'Originally a 2026 campaign commitment',
				'type'    => 'select',
				'options' => array(
					''    => 'No',
					'yes' => 'Yes — label it as historical context',
				),
			),
			'tlharris_historical_target' => array(
				'label' => 'Original campaign wording (historical context only, never present tense)',
				'type'  => 'textarea',
			),
			// Numeric fields drive the only progress visualisation on the site.
			// All three must be present and numeric, and the evidence URL above
			// must be set, or nothing is drawn: a progress bar with no real
			// baseline, or no source for it, is an invented claim.
			'tlharris_measure_unit'      => array( 'label' => 'Measure (what the numbers count)', 'type' => 'text' ),
			'tlharris_baseline_value'    => array( 'label' => 'Baseline value (number)', 'type' => 'text' ),
			'tlharris_current_value'     => array( 'label' => 'Current value (number)', 'type' => 'text' ),
			'tlharris_target_value'      => array( 'label' => 'Target value (number)', 'type' => 'text' ),
		) + $source,
		'board_action'    => array(
			'tlharris_action_date'   => array( 'label' => 'Action date', 'type' => 'date' ),
			'tlharris_vote_position' => array(
				'label'   => 'Documented position',
				'type'    => 'select',
				'options' => array(
					''        => 'Not documented',
					'yes'     => 'Voted yes',
					'no'      => 'Voted no',
					'abstain' => 'Abstained',
					'absent'  => 'Absent',
					'present' => 'Present, not voting',
				),
			),
		) + $source,
		'board_update'    => $source,
		'board_document'  => array(
			'tlharris_document_type' => array( 'label' => 'Document type', 'type' => 'text' ),
			'tlharris_document_date' => array( 'label' => 'Document date', 'type' => 'date' ),
			'tlharris_document_url'  => array( 'label' => 'Official document URL', 'type' => 'url' ),
		) + $source,
		'committee'       => array(
			'tlharris_committee_role'  => array( 'label' => 'Role on committee', 'type' => 'text' ),
			'tlharris_term_start'      => array( 'label' => 'Term start', 'type' => 'date' ),
			'tlharris_term_end'        => array( 'label' => 'Term end', 'type' => 'date' ),
		) + $source,
		'event'           => array(
			'tlharris_event_date'     => array( 'label' => 'Event date', 'type' => 'date' ),
			'tlharris_event_time'     => array( 'label' => 'Start time (as published by the source)', 'type' => 'text' ),
			'tlharris_event_end_date' => array( 'label' => 'End date', 'type' => 'date' ),
			'tlharris_public_comment' => array(
				'label'   => 'Public comment offered',
				'type'    => 'select',
				'options' => array(
					''    => 'Not stated',
					'yes' => 'Yes',
					'no'  => 'No',
				),
			),
			'tlharris_event_type'     => array(
				'label'   => 'Event type',
				'type'    => 'select',
				'options' => array(
					''             => 'Unspecified',
					'board'        => 'Board meeting',
					'town-hall'    => 'Town hall',
					'school-visit' => 'School visit',
					'community'    => 'Community event',
				),
			),
			'tlharris_event_location' => array( 'label' => 'Location', 'type' => 'text' ),
			'tlharris_rsvp_url'       => array( 'label' => 'RSVP URL', 'type' => 'url' ),
			'tlharris_recap_summary'  => array( 'label' => 'Recap summary (past events)', 'type' => 'textarea' ),
			'tlharris_recap_followup' => array( 'label' => 'Follow-up actions (past events)', 'type' => 'textarea' ),
		) + $source,
		'district_school' => array(
			'tlharris_school_type'  => array( 'label' => 'School type', 'type' => 'text' ),
			'tlharris_grades'       => array( 'label' => 'Grades served', 'type' => 'text' ),
			'tlharris_principal'    => array( 'label' => 'Principal', 'type' => 'text' ),
			'tlharris_address'      => array( 'label' => 'Address', 'type' => 'textarea' ),
			'tlharris_phone'        => array( 'label' => 'Phone', 'type' => 'text' ),
			'tlharris_official_url' => array( 'label' => 'Official school URL', 'type' => 'url' ),
		) + $source,
		'press_item'      => array(
			'tlharris_outlet'           => array( 'label' => 'Outlet', 'type' => 'text' ),
			'tlharris_publication_date' => array( 'label' => 'Publication date', 'type' => 'date' ),
		) + $source,
	);
}

function tlharris_register_meta() {
	foreach ( tlharris_meta_fields() as $post_type => $fields ) {
		foreach ( $fields as $key => $field ) {
			register_post_meta(
				$post_type,
				$key,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'default'           => '',
					'description'       => $field['label'],
					'sanitize_callback' => 'tlharris_sanitize_meta_value',
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}
}
add_action( 'init', 'tlharris_register_meta' );

/**
 * Registered sanitizer. Type-aware validation happens on save; this is the
 * floor for anything arriving through REST.
 */
function tlharris_sanitize_meta_value( $value ) {
	return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );
}

function tlharris_add_meta_boxes() {
	foreach ( tlharris_meta_fields() as $post_type => $fields ) {
		add_meta_box(
			'tlharris-record-' . $post_type,
			'Public Record Details',
			'tlharris_render_meta_box',
			$post_type,
			'normal',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'tlharris_add_meta_boxes' );

function tlharris_render_meta_box( $post ) {
	$fields = tlharris_meta_fields()[ $post->post_type ] ?? array();
	if ( ! $fields ) {
		return;
	}

	wp_nonce_field( 'tlharris_save_meta', 'tlharris_meta_nonce' );

	echo '<p class="description">Do not invent values. Every claim needs an official source and a verification date.</p>';
	echo '<table class="form-table" role="presentation">';

	foreach ( $fields as $key => $field ) {
		$value = (string) get_post_meta( $post->ID, $key, true );
		$id    = 'tlharris-field-' . $key;

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';

		if ( 'textarea' === $field['type'] ) {
			printf(
				'<textarea class="large-text" rows="3" id="%s" name="%s">%s</textarea>',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_textarea( $value )
			);
		} elseif ( 'select' === $field['type'] ) {
			printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $key ) );
			foreach ( $field['options'] as $option_value => $option_label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $option_value ),
					selected( $value, $option_value, false ),
					esc_html( $option_label )
				);
			}
			echo '</select>';
		} else {
			$input_type = 'text';
			if ( 'url' === $field['type'] ) {
				$input_type = 'url';
			} elseif ( 'date' === $field['type'] ) {
				$input_type = 'date';
			}
			printf(
				'<input class="regular-text" type="%s" id="%s" name="%s" value="%s">',
				esc_attr( $input_type ),
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $value )
			);
		}

		echo '</td></tr>';
	}

	echo '</table>';
}

function tlharris_save_meta( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	$fields = tlharris_meta_fields()[ $post->post_type ] ?? array();
	if ( ! $fields ) {
		return;
	}

	if ( ! isset( $_POST['tlharris_meta_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tlharris_meta_nonce'] ) ), 'tlharris_save_meta' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( $fields as $key => $field ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}

		$raw = wp_unslash( $_POST[ $key ] );

		if ( 'select' === $field['type'] ) {
			$value = array_key_exists( $raw, $field['options'] ) ? $raw : '';
		} else {
			$value = tlharris_sanitize_identity_value( $raw, $field['type'] );
		}

		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
add_action( 'save_post', 'tlharris_save_meta', 10, 2 );

/* -------------------------------------------------------------------------
 * Query behaviour
 * ---------------------------------------------------------------------- */

/**
 * Carry a Query block's className down to the blocks that build its query.
 *
 * Templates opt a Query block in to custom behaviour by giving it a className.
 * But core does not hand the query_loop_block_query_vars filter the Query block.
 * It hands it whichever block is asking: the Post Template, the pagination
 * blocks, the "no results" block. Those never see the Query block's className,
 * so a filter that reads only $block->attributes never matches, and every list
 * silently shows the unfiltered query.
 *
 * Copying the class into block context fixes that for the whole subtree. It is
 * passed down one level at a time because a grandchild (pagination numbers sit
 * inside the pagination block) only receives the keys its own block type asks
 * for.
 *
 * @param array         $context      Block context of the block about to render.
 * @param array         $parsed_block The block about to render.
 * @param WP_Block|null $parent_block Its parent.
 * @return array
 */
function tlharris_pass_query_class( $context, $parsed_block, $parent_block ) {
	if ( ! $parent_block instanceof WP_Block ) {
		return $context;
	}

	if ( 'core/query' === $parent_block->name ) {
		$class = $parent_block->attributes['className'] ?? '';

		if ( is_string( $class ) && '' !== $class ) {
			$context['tlharris/queryClass'] = $class;
		} else {
			unset( $context['tlharris/queryClass'] );
		}
	} elseif ( isset( $parent_block->context['tlharris/queryClass'] ) && ! isset( $context['tlharris/queryClass'] ) ) {
		$context['tlharris/queryClass'] = $parent_block->context['tlharris/queryClass'];
	}

	return $context;
}
add_filter( 'render_block_context', 'tlharris_pass_query_class', 10, 3 );

/**
 * Events sort by the date the event happens, not the date the post was
 * published. Query blocks opt in with a className, which
 * tlharris_pass_query_class() makes visible here.
 *
 * @param array    $query Query vars.
 * @param WP_Block $block Block instance asking for the query.
 * @return array
 */
function tlharris_filter_query_loop( $query, $block ) {
	$class = trim(
		( is_string( $block->attributes['className'] ?? null ) ? $block->attributes['className'] : '' )
		. ' '
		. ( is_string( $block->context['tlharris/queryClass'] ?? null ) ? $block->context['tlharris/queryClass'] : '' )
	);

	if ( str_contains( $class, 'tlharris-query-upcoming-events' ) ) {
		$query['meta_key'] = 'tlharris_event_date';
		$query['orderby']  = 'meta_value';
		$query['order']    = 'ASC';
		$query['meta_query'] = array(
			array(
				'key'     => 'tlharris_event_date',
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			),
		);
	}

	if ( str_contains( $class, 'tlharris-query-past-events' ) ) {
		$query['meta_key'] = 'tlharris_event_date';
		$query['orderby']  = 'meta_value';
		$query['order']    = 'DESC';
		$query['meta_query'] = array(
			array(
				'key'     => 'tlharris_event_date',
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '<',
				'type'    => 'DATE',
			),
		);
	}

	foreach ( array( 'elementary', 'middle', 'high' ) as $level ) {
		if ( str_contains( $class, 'tlharris-query-schools-' . $level ) ) {
			$query['orderby']   = 'title';
			$query['order']     = 'ASC';
			$query['tax_query'] = array(
				array(
					'taxonomy' => 'school_level',
					'field'    => 'slug',
					'terms'    => array( $level ),
				),
			);
		}
	}

	// Priorities most recently verified against a source first. A record with no
	// verification date is left out: listing it here would imply it had been
	// checked.
	if ( str_contains( $class, 'tlharris-query-priorities-recent' ) ) {
		$query['meta_key']   = 'tlharris_last_verified';
		$query['orderby']    = 'meta_value';
		$query['order']      = 'DESC';
		$query['meta_query'] = array(
			array(
				'key'     => 'tlharris_last_verified',
				'value'   => '',
				'compare' => '!=',
			),
		);
	}

	// Priorities with an update date, oldest date first. A date that has passed
	// stays in the list, and sorts to the top: an overdue update is the one a
	// reader most needs to see, so it must not drop out the day it becomes late.
	if ( str_contains( $class, 'tlharris-query-priorities-upcoming' ) ) {
		$query['meta_key']   = 'tlharris_next_update';
		$query['orderby']    = 'meta_value';
		$query['order']      = 'ASC';
		$query['meta_query'] = array(
			array(
				'key'     => 'tlharris_next_update',
				'value'   => '',
				'compare' => '!=',
			),
		);
	}

	/*
	 * Board work related to the priority being viewed: anything sharing one of
	 * its topic terms. Falls back to returning nothing rather than to returning
	 * everything, which would read as a claim that all of it is related.
	 */
	if ( str_contains( $class, 'tlharris-query-related' ) ) {
		$terms = wp_get_object_terms( get_queried_object_id(), 'board_topic', array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $terms ) || ! $terms ) {
			$query['post__in'] = array( 0 );
		} else {
			$query['tax_query'] = array(
				array(
					'taxonomy' => 'board_topic',
					'field'    => 'slug',
					'terms'    => $terms,
				),
			);
		}
	}

	// Term slugs, not term IDs: IDs are not portable between environments.
	if ( str_contains( $class, 'tlharris-query-report' ) ) {
		$query['tax_query'] = array(
			array(
				'taxonomy' => 'update_type',
				'field'    => 'slug',
				'terms'    => array( 'report-to-district-6' ),
			),
		);
	}

	if ( str_contains( $class, 'tlharris-query-not-report' ) ) {
		$query['tax_query'] = array(
			array(
				'taxonomy' => 'update_type',
				'field'    => 'slug',
				'terms'    => array( 'report-to-district-6' ),
				'operator' => 'NOT IN',
			),
		);
	}

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'tlharris_filter_query_loop', 10, 2 );

/* -------------------------------------------------------------------------
 * Accountability rendering
 *
 * Two shortcodes rather than custom blocks, because both need to render
 * conditionally on server-side data and neither justifies a JavaScript build
 * step. Shortcodes inside block templates are processed by core.
 * ---------------------------------------------------------------------- */

/**
 * Whether a string is a usable public web link: http or https, a dotted host,
 * no embedded credentials, no whitespace.
 *
 * Deliberately not wp_http_validate_url(). That function is built to vet a URL
 * before the server fetches it, so it resolves the host over DNS on every call
 * and rejects any host that does not resolve at that moment. Run on each page
 * view it adds a network lookup to a page render, and it rejects perfectly good
 * links wherever the server cannot resolve names, such as a sandboxed
 * browser-based environment. A link that is only displayed needs a syntax
 * check, not a network one.
 *
 * @param mixed $url Candidate URL.
 */
function tlharris_is_public_url( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url || preg_match( '/\s/', $url ) ) {
		return false;
	}

	$parts = wp_parse_url( $url );

	return is_array( $parts )
		&& isset( $parts['scheme'], $parts['host'] )
		&& in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
		&& str_contains( $parts['host'], '.' )
		&& ! isset( $parts['user'] )
		&& ! isset( $parts['pass'] );
}

/**
 * A progress visualisation, and only when the numbers behind it are real.
 *
 * Place it in a Custom HTML block, not a Shortcode block. Core expands
 * shortcodes in a template before the blocks render, then runs wpautop() over
 * the Shortcode block's content, and wpautop() leaves stray closing </p> tags in
 * markup that spans several lines. A Custom HTML block is not autop'd.
 *
 * Requires a numeric baseline, current value and target, and an evidence link
 * to the source of those figures. Missing any one of them, this renders the
 * reason instead of a bar. An invented progress bar is the single most
 * dishonest thing an accountability tracker can do, and a bar with numbers but
 * no source is the same thing with better manners.
 */
function tlharris_progress_shortcode( $atts ) {
	$post_id = get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	$baseline = get_post_meta( $post_id, 'tlharris_baseline_value', true );
	$current  = get_post_meta( $post_id, 'tlharris_current_value', true );
	$target   = get_post_meta( $post_id, 'tlharris_target_value', true );
	$unit     = (string) get_post_meta( $post_id, 'tlharris_measure_unit', true );
	$evidence = trim( (string) get_post_meta( $post_id, 'tlharris_evidence_url', true ) );

	$have_numbers = is_numeric( $baseline ) && is_numeric( $current ) && is_numeric( $target );

	if ( ! $have_numbers ) {
		return '<p class="tlharris-source">'
			. esc_html__( 'Baseline not yet established from a verified public source. No progress figure is shown.', 'tlharris-core' )
			. '</p>';
	}

	if ( ! tlharris_is_public_url( $evidence ) ) {
		return '<p class="tlharris-source">'
			. esc_html__( 'Figures have been entered, but no evidence link is recorded for them. No progress figure is shown until the source is linked.', 'tlharris-core' )
			. '</p>';
	}

	$baseline = (float) $baseline;
	$current  = (float) $current;
	$target   = (float) $target;
	$span     = $target - $baseline;

	if ( 0.0 === $span ) {
		return '<p class="tlharris-source">'
			. esc_html__( 'The baseline and the target are the same, so no movement can be shown.', 'tlharris-core' )
			. '</p>';
	}

	$fraction = ( $current - $baseline ) / $span;
	$percent  = (int) round( max( 0, min( 1, $fraction ) ) * 100 );

	$label = $unit
		? sprintf(
			/* translators: 1: measure, 2: baseline, 3: current, 4: target */
			__( '%1$s: baseline %2$s, currently %3$s, target %4$s.', 'tlharris-core' ),
			$unit,
			$baseline,
			$current,
			$target
		)
		: sprintf(
			/* translators: 1: baseline, 2: current, 3: target */
			__( 'Baseline %1$s, currently %2$s, target %3$s.', 'tlharris-core' ),
			$baseline,
			$current,
			$target
		);

	ob_start();
	?>
	<div class="tlharris-progress">
		<p class="tlharris-progress__label"><?php echo esc_html( $label ); ?></p>
		<div class="tlharris-progress__track" role="img" aria-label="<?php echo esc_attr( sprintf( __( '%d percent of the distance from baseline to target.', 'tlharris-core' ), $percent ) ); ?>">
			<div class="tlharris-progress__fill" style="width:<?php echo esc_attr( $percent ); ?>%"></div>
		</div>
		<p class="tlharris-source">
			<?php
			printf(
				/* translators: %d: percentage */
				esc_html__( '%d%% of the distance from the baseline to the target. This measures movement between two published figures, not whether the goal will be met.', 'tlharris-core' ),
				(int) $percent
			);
			?>
		</p>
		<p class="tlharris-source">
			<?php esc_html_e( 'Figures from:', 'tlharris-core' ); ?>
			<a href="<?php echo esc_url( $evidence ); ?>"><?php echo esc_html( $evidence ); ?></a>
		</p>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'tlharris_progress', 'tlharris_progress_shortcode' );

/**
 * A stored Y-m-d date as a readable date, or null when the value is not one.
 *
 * Runs the value through tlharris_sanitize_date(), so the check that guards what
 * is saved is the check that guards what is shown. Anything else is left for the
 * caller to print as it was entered.
 *
 * @param mixed $value Stored meta value.
 * @return array{text:string,iso:string}|null
 */
function tlharris_readable_date( $value ) {
	$iso = tlharris_sanitize_date( $value );

	if ( '' === $iso ) {
		return null;
	}

	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $iso, wp_timezone() );

	if ( ! $date ) {
		return null;
	}

	return array(
		'text' => wp_date( get_option( 'date_format' ), $date->getTimestamp() ),
		'iso'  => $iso,
	);
}

/**
 * The record fields that hold a date and are shown as one.
 *
 * @return string[]
 */
function tlharris_date_field_keys() {
	return array( 'last_verified', 'next_update', 'target_date' );
}

/**
 * Block binding that reads one of a record's date fields, formatted for the
 * site, so a card inside a query loop can show the date the record actually has.
 *
 * Shortcodes cannot do this job. Core evaluates them after the whole template
 * has rendered, when the current post is the page and not the loop item, so a
 * shortcode in a card sees the wrong record. A binding is resolved as its block
 * renders, against that item's own post ID.
 *
 * Returns null, which leaves the block empty, when the field is unset or is not
 * a real date. The queries that feed these cards leave such records out.
 *
 * Usage in a template:
 * <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"tlharris/date","args":{"key":"next_update"}}}}} -->
 */
function tlharris_register_date_binding() {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}

	register_block_bindings_source(
		'tlharris/date',
		array(
			'label'              => 'T. L. Harris record date',
			'get_value_callback' => 'tlharris_date_binding_value',
			'uses_context'       => array( 'postId' ),
		)
	);
}
add_action( 'init', 'tlharris_register_date_binding' );

/**
 * @param array    $source_args    Expects a `key` from tlharris_date_field_keys().
 * @param WP_Block $block_instance The block being bound.
 * @return string|null
 */
function tlharris_date_binding_value( $source_args, $block_instance ) {
	$key = is_array( $source_args ) && isset( $source_args['key'] ) ? (string) $source_args['key'] : '';

	if ( ! in_array( $key, tlharris_date_field_keys(), true ) ) {
		return null;
	}

	$post_id = isset( $block_instance->context['postId'] ) ? (int) $block_instance->context['postId'] : (int) get_the_ID();

	if ( ! $post_id ) {
		return null;
	}

	$date = tlharris_readable_date( get_post_meta( $post_id, 'tlharris_' . $key, true ) );

	return $date ? $date['text'] : null;
}

/**
 * Render one record field, with an honest fallback when it is empty.
 *
 * Block bindings cannot express a fallback: an empty meta value renders as an
 * empty paragraph, which reads as though the question was never asked. Every
 * field on a priority says either what is known or that it is not yet
 * established from a verified source.
 *
 * Usage: [tlharris_field key="baseline" label="Baseline"]
 */
function tlharris_field_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'key'   => '',
			'label' => '',
			'empty' => '',
			'level' => '3',
		),
		$atts,
		'tlharris_field'
	);

	$post_id = get_the_ID();
	$key     = preg_replace( '/[^a-z0-9_]/', '', (string) $atts['key'] );

	if ( ! $post_id || '' === $key ) {
		return '';
	}

	$value = trim( (string) get_post_meta( $post_id, 'tlharris_' . $key, true ) );
	$level = max( 2, min( 4, (int) $atts['level'] ) );

	$fallback = $atts['empty']
		? $atts['empty']
		: __( 'Not yet established from a verified public source.', 'tlharris-core' );

	$out = '<div class="tlharris-field">';

	if ( $atts['label'] ) {
		$out .= sprintf(
			'<h%1$d class="tlharris-field__label">%2$s</h%1$d>',
			$level,
			esc_html( $atts['label'] )
		);
	}

	$date = in_array( $key, tlharris_date_field_keys(), true )
		? tlharris_readable_date( $value )
		: null;

	if ( '' === $value ) {
		$out .= '<p class="tlharris-source">' . esc_html( $fallback ) . '</p>';
	} elseif ( in_array( $key, array( 'evidence_url', 'source_url' ), true ) ) {
		$out .= '<p><a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a></p>';
	} elseif ( $date ) {
		$out .= '<p><time datetime="' . esc_attr( $date['iso'] ) . '">' . esc_html( $date['text'] ) . '</time></p>';
	} else {
		$out .= wpautop( esc_html( $value ) );
	}

	return $out . '</div>';
}
add_shortcode( 'tlharris_field', 'tlharris_field_shortcode' );

/**
 * Counts of tracked priorities by status.
 *
 * Place it in a Custom HTML block, not a Shortcode block: see
 * tlharris_progress_shortcode() for why.
 *
 * These are counts of this site's own records, not claims about district
 * outcomes. Nothing here is a performance figure.
 */
function tlharris_priority_stats_shortcode( $atts ) {
	$statuses = get_terms(
		array(
			'taxonomy'   => 'progress_status',
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $statuses ) ) {
		return '';
	}

	$total = (int) wp_count_posts( 'priority' )->publish;

	if ( 0 === $total ) {
		return '<p class="tlharris-source">'
			. esc_html__( 'No priorities are published yet.', 'tlharris-core' )
			. '</p>';
	}

	$by_name = array();
	foreach ( $statuses as $status ) {
		$by_name[ $status->name ] = $status;
	}

	// Walk the fixed lifecycle order (Not Started ... On Hold), never the
	// alphabetical order get_terms() returns. Alphabetical happens to put
	// "Completed" first and "Not Started" fourth, which reads as a ranking
	// of how well things are going. This tracker does not rank itself.
	$rows = array();
	foreach ( tlharris_progress_status_labels() as $name ) {
		if ( ! isset( $by_name[ $name ] ) ) {
			continue;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'priority',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array(
					array(
						'taxonomy' => 'progress_status',
						'field'    => 'term_id',
						'terms'    => $by_name[ $name ]->term_id,
					),
				),
			)
		);

		if ( $ids ) {
			$rows[ $name ] = count( $ids );
		}
	}

	ob_start();
	?>
	<div class="tlharris-stats">
		<div class="tlharris-stat">
			<span class="tlharris-stat__number"><?php echo esc_html( (string) $total ); ?></span>
			<span class="tlharris-stat__label"><?php esc_html_e( 'priorities tracked', 'tlharris-core' ); ?></span>
		</div>
		<?php foreach ( $rows as $name => $count ) : ?>
			<div class="tlharris-stat">
				<span class="tlharris-stat__number"><?php echo esc_html( (string) $count ); ?></span>
				<span class="tlharris-stat__label"><?php echo esc_html( strtolower( $name ) ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="tlharris-source">
		<?php esc_html_e( 'These are counts of the priorities tracked on this site, listed in the order a record moves through them, not a ranking of how well things are going. They are not district performance figures.', 'tlharris-core' ); ?>
	</p>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'tlharris_priority_stats', 'tlharris_priority_stats_shortcode' );

/**
 * The historical-context label for a priority that began as a campaign
 * commitment. Renders nothing unless the record is flagged.
 */
function tlharris_campaign_origin_shortcode( $atts ) {
	$post_id = get_the_ID();

	if ( ! $post_id || 'yes' !== get_post_meta( $post_id, 'tlharris_campaign_origin', true ) ) {
		return '';
	}

	$historical = trim( (string) get_post_meta( $post_id, 'tlharris_historical_target', true ) );

	$out = '<p class="tlharris-source">'
		. esc_html__( 'Originally identified during the 2026 campaign; now tracked as a public-service priority.', 'tlharris-core' )
		. '</p>';

	if ( $historical ) {
		$out .= '<blockquote class="tlharris-historical"><p>' . esc_html( $historical ) . '</p>'
			. '<cite>' . esc_html__( 'Campaign wording, kept as historical context. It is not a current commitment or a predicted outcome.', 'tlharris-core' ) . '</cite>'
			. '</blockquote>';
	}

	return $out;
}
add_shortcode( 'tlharris_campaign_origin', 'tlharris_campaign_origin_shortcode' );

/* -------------------------------------------------------------------------
 * Verified content importer
 *
 * Verified facts live in `content/*.json` in the repository, where they can be
 * reviewed and diffed, and are imported into the CMS as entries. Templates
 * never hardcode a school or a meeting date.
 *
 * Everything imports as a DRAFT. A transcription is not a verification: a human
 * checks each entry against the live source before it is published.
 * ---------------------------------------------------------------------- */

function tlharris_content_dir() {
	/**
	 * Filters where the importer looks for its JSON data files.
	 *
	 * Defaults to `content/` three levels up from the plugin, which is correct
	 * when the repository is checked out whole. On a site where only
	 * `wp-content` was deployed, point this at the files or skip the import.
	 *
	 * @param string $dir Absolute path.
	 */
	return apply_filters( 'tlharris_content_dir', dirname( __DIR__, 3 ) . '/content' );
}

function tlharris_read_json( $filename ) {
	$path = tlharris_content_dir() . '/' . $filename;

	if ( ! is_readable( $path ) ) {
		return new WP_Error( 'tlharris_missing_file', sprintf( 'Cannot read %s', $path ) );
	}

	$data = json_decode( file_get_contents( $path ), true );

	if ( ! is_array( $data ) ) {
		return new WP_Error( 'tlharris_bad_json', sprintf( '%s is not valid JSON', $filename ) );
	}

	return $data;
}

/**
 * Find an existing entry by its stable import key so re-running the importer
 * updates rather than duplicates.
 */
function tlharris_find_by_import_key( $post_type, $key ) {
	$found = get_posts(
		array(
			'post_type'        => $post_type,
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => array(
				array(
					'key'   => 'tlharris_import_key',
					'value' => $key,
				),
			),
		)
	);

	return $found ? (int) $found[0] : 0;
}

/**
 * @return array{created:int,updated:int,errors:string[]}
 */
function tlharris_import_schools() {
	$result = array( 'created' => 0, 'updated' => 0, 'errors' => array() );
	$data   = tlharris_read_json( 'district-6-schools.json' );

	if ( is_wp_error( $data ) ) {
		$result['errors'][] = $data->get_error_message();
		return $result;
	}

	$source   = $data['_source'] ?? '';
	$accessed = $data['_accessed'] ?? '';

	foreach ( $data['schools'] ?? array() as $school ) {
		$name = trim( (string) ( $school['name'] ?? '' ) );
		if ( '' === $name ) {
			continue;
		}

		$key       = 'school:' . sanitize_title( $name );
		$existing  = tlharris_find_by_import_key( 'district_school', $key );
		$post_data = array(
			'post_type'  => 'district_school',
			'post_title' => $name,
			'post_name'  => sanitize_title( $name ),
		);

		if ( $existing ) {
			$post_data['ID'] = $existing;
			$post_id         = wp_update_post( $post_data, true );
		} else {
			// Draft: nothing published until a human has checked it.
			$post_data['post_status'] = 'draft';
			$post_id                  = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			$result['errors'][] = sprintf( '%s: %s', $name, $post_id->get_error_message() );
			continue;
		}

		update_post_meta( $post_id, 'tlharris_import_key', $key );

		if ( ! empty( $school['url'] ) ) {
			update_post_meta( $post_id, 'tlharris_official_url', esc_url_raw( $school['url'] ) );
		}
		if ( $source ) {
			update_post_meta( $post_id, 'tlharris_source_url', esc_url_raw( $source ) );
		}
		if ( $accessed ) {
			update_post_meta( $post_id, 'tlharris_last_verified', tlharris_sanitize_date( $accessed ) );
		}

		$levels = array_filter( array_map( 'strval', (array) ( $school['levels'] ?? array() ) ) );
		if ( $levels ) {
			wp_set_object_terms( $post_id, $levels, 'school_level', false );
		}

		$existing ? $result['updated']++ : $result['created']++;
	}

	return $result;
}

/**
 * @return array{created:int,updated:int,errors:string[]}
 */
function tlharris_import_meetings() {
	$result = array( 'created' => 0, 'updated' => 0, 'errors' => array() );
	$data   = tlharris_read_json( 'board-meetings.json' );

	if ( is_wp_error( $data ) ) {
		$result['errors'][] = $data->get_error_message();
		return $result;
	}

	$source   = $data['_source'] ?? '';
	$accessed = $data['_accessed'] ?? '';

	foreach ( $data['meetings'] ?? array() as $meeting ) {
		$title = trim( (string) ( $meeting['title'] ?? '' ) );
		$date  = tlharris_sanitize_date( $meeting['date'] ?? '' );

		if ( '' === $title || '' === $date ) {
			$result['errors'][] = sprintf( 'Skipped a meeting with a missing title or invalid date (%s)', $title );
			continue;
		}

		$key       = 'meeting:' . $date . ':' . sanitize_title( $title );
		$existing  = tlharris_find_by_import_key( 'event', $key );
		$post_data = array(
			'post_type'  => 'event',
			'post_title' => $title,
			'post_name'  => sanitize_title( $date . '-' . $title ),
		);

		if ( $existing ) {
			$post_data['ID'] = $existing;
			$post_id         = wp_update_post( $post_data, true );
		} else {
			$post_data['post_status'] = 'draft';
			$post_id                  = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			$result['errors'][] = sprintf( '%s: %s', $title, $post_id->get_error_message() );
			continue;
		}

		update_post_meta( $post_id, 'tlharris_import_key', $key );
		update_post_meta( $post_id, 'tlharris_event_date', $date );
		update_post_meta( $post_id, 'tlharris_event_type', 'board' );

		if ( ! empty( $meeting['time'] ) ) {
			update_post_meta( $post_id, 'tlharris_event_time', sanitize_text_field( $meeting['time'] ) );
		}
		if ( ! empty( $meeting['location'] ) ) {
			update_post_meta( $post_id, 'tlharris_event_location', sanitize_text_field( $meeting['location'] ) );
		}
		if ( isset( $meeting['public_comment'] ) ) {
			update_post_meta( $post_id, 'tlharris_public_comment', $meeting['public_comment'] ? 'yes' : 'no' );
		}
		if ( $source ) {
			update_post_meta( $post_id, 'tlharris_source_url', esc_url_raw( $source ) );
		}
		if ( $accessed ) {
			update_post_meta( $post_id, 'tlharris_last_verified', tlharris_sanitize_date( $accessed ) );
		}

		$existing ? $result['updated']++ : $result['created']++;
	}

	return $result;
}

/**
 * Priority subjects. The seed file carries the subject, the category and the
 * governance framing only. It deliberately carries no baseline, no target, no
 * date and no action: none of those exist from a verified source yet, and an
 * accountability tracker that starts with invented numbers is worse than one
 * that starts empty.
 *
 * Create-only, by office decision (2026-09-21): a normal re-import creates
 * any priority missing from the CMS but never touches one that already
 * exists, so office edits to a published or in-review record are never
 * silently overwritten by the seed file. A skipped record is reported by
 * title so an editor can see what the import left alone. A genuine,
 * intentional change to the source data (fixing a typo in the seed, adding
 * a new field to every record) is a separate, explicit migration, not a
 * re-run of this import — see docs/content-sources.md.
 *
 * @return array{created:int,skipped:int,skipped_titles:string[],errors:string[]}
 */
function tlharris_import_priorities() {
	$result = array( 'created' => 0, 'skipped' => 0, 'skipped_titles' => array(), 'errors' => array() );
	$data   = tlharris_read_json( 'priorities.json' );

	if ( is_wp_error( $data ) ) {
		$result['errors'][] = $data->get_error_message();
		return $result;
	}

	$text_fields = array(
		'commitment', 'why_it_matters', 'baseline', 'target', 'role',
		'direct_control', 'influence', 'district_context', 'action_taken',
		'evidence_note', 'what_next', 'historical_target', 'measure_unit',
	);

	foreach ( $data['priorities'] ?? array() as $priority ) {
		$title = trim( (string) ( $priority['title'] ?? '' ) );

		if ( '' === $title ) {
			continue;
		}

		$key      = 'priority:' . sanitize_title( $title );
		$existing = tlharris_find_by_import_key( 'priority', $key );

		if ( $existing ) {
			// Already in the CMS. Leave title, excerpt, governance text,
			// status and every other field exactly as an editor left them.
			$result['skipped']++;
			$result['skipped_titles'][] = $title;
			continue;
		}

		$post_data = array(
			'post_type'    => 'priority',
			'post_title'   => $title,
			'post_name'    => sanitize_title( $title ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $priority['description'] ?? '' ) ),
			// Draft: nothing published until a human has checked it.
			'post_status'  => 'draft',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$result['errors'][] = sprintf( '%s: %s', $title, $post_id->get_error_message() );
			continue;
		}

		update_post_meta( $post_id, 'tlharris_import_key', $key );

		foreach ( $text_fields as $field ) {
			if ( ! empty( $priority[ $field ] ) ) {
				update_post_meta( $post_id, 'tlharris_' . $field, sanitize_textarea_field( (string) $priority[ $field ] ) );
			}
		}

		if ( ! empty( $priority['campaign_origin'] ) ) {
			update_post_meta( $post_id, 'tlharris_campaign_origin', 'yes' );
		}

		if ( ! empty( $priority['status'] ) ) {
			wp_set_object_terms( $post_id, array( (string) $priority['status'] ), 'progress_status', false );
		}

		if ( ! empty( $priority['category'] ) ) {
			wp_set_object_terms( $post_id, array( (string) $priority['category'] ), 'priority_category', false );
		}

		if ( ! empty( $priority['topics'] ) ) {
			wp_set_object_terms( $post_id, array_map( 'strval', (array) $priority['topics'] ), 'board_topic', false );
		}

		$result['created']++;
	}

	return $result;
}

function tlharris_register_import_page() {
	add_submenu_page(
		'tlharris-public-record',
		'Import Verified Content',
		'Import Verified Content',
		'manage_options',
		'tlharris-import',
		'tlharris_render_import_page'
	);
}
add_action( 'admin_menu', 'tlharris_register_import_page' );

function tlharris_render_import_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$messages = array();

	if ( isset( $_POST['tlharris_import_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tlharris_import_nonce'] ) ), 'tlharris_import' ) ) {

		$schools    = tlharris_import_schools();
		$meetings   = tlharris_import_meetings();
		$priorities = tlharris_import_priorities();

		$messages[] = sprintf(
			'Schools: %d created, %d updated. Meetings: %d created, %d updated. Priorities: %d created, %d already in the CMS (left alone).',
			$schools['created'],
			$schools['updated'],
			$meetings['created'],
			$meetings['updated'],
			$priorities['created'],
			$priorities['skipped']
		);

		if ( ! empty( $priorities['skipped_titles'] ) ) {
			$messages[] = 'Priorities left alone: ' . implode( '; ', $priorities['skipped_titles'] );
		}

		foreach ( array_merge( $schools['errors'], $meetings['errors'], $priorities['errors'] ) as $error ) {
			$messages[] = 'Error: ' . $error;
		}
	}
	?>
	<div class="wrap">
		<h1>Import Verified Content</h1>
		<?php foreach ( $messages as $message ) : ?>
			<div class="notice notice-info"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endforeach; ?>
		<p>
			Loads the District 6 school list, the Board meeting list and the eight
			priority subjects from the repository's <code>content/</code> folder into
			the CMS.
		</p>
		<p>
			For schools and meetings, running it again updates the existing entries
			from the file instead of duplicating them.
		</p>
		<p>
			<strong>For priorities, running it again never overwrites your edits.</strong>
			A priority already in the CMS — by title — is left exactly as an editor
			left it: title, excerpt, governance text, status, everything. Only a
			priority missing from the CMS is created. The notice above lists what was
			skipped, so you can see what the import left alone. To make an
			intentional change to the source data itself (fixing a seed file typo,
			adding a field to every record), edit the record directly in the CMS, or
			see the update/migration process in <code>docs/content-sources.md</code> —
			re-running this import is not that process.
		</p>
		<p>
			<strong>Everything imports as a draft.</strong> A transcription is not a
			verification. Check each entry against the official source before
			publishing it.
		</p>
		<form method="post">
			<?php wp_nonce_field( 'tlharris_import', 'tlharris_import_nonce' ); ?>
			<?php submit_button( 'Import now' ); ?>
		</form>
	</div>
	<?php
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'tlharris import',
		function () {
			$schools    = tlharris_import_schools();
			$meetings   = tlharris_import_meetings();
			$priorities = tlharris_import_priorities();

			foreach ( array_merge( $schools['errors'], $meetings['errors'], $priorities['errors'] ) as $error ) {
				WP_CLI::warning( $error );
			}

			if ( ! empty( $priorities['skipped_titles'] ) ) {
				WP_CLI::log( 'Priorities already in the CMS, left alone: ' . implode( '; ', $priorities['skipped_titles'] ) );
			}

			WP_CLI::success(
				sprintf(
					'Schools: %d created, %d updated. Meetings: %d created, %d updated. Priorities: %d created, %d already in the CMS (left alone). All drafts.',
					$schools['created'],
					$schools['updated'],
					$meetings['created'],
					$meetings['updated'],
					$priorities['created'],
					$priorities['skipped']
				)
			);
		}
	);
}

/* -------------------------------------------------------------------------
 * Reserved routes
 * ---------------------------------------------------------------------- */

/**
 * The reserved story route stays out of search and sitemaps until the office
 * approves it. Keyed on a stored page ID, so renaming the slug cannot
 * silently un-reserve it.
 */
function tlharris_reserved_page_ids() {
	$id = (int) get_option( 'tlharris_my_story_page_id', 0 );
	return $id ? array( $id ) : array();
}

function tlharris_filter_robots( $robots ) {
	if ( is_page( tlharris_reserved_page_ids() ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'tlharris_filter_robots' );

function tlharris_exclude_reserved_from_sitemap( $args ) {
	$ids = tlharris_reserved_page_ids();
	if ( $ids ) {
		$args['post__not_in'] = array_merge( $args['post__not_in'] ?? array(), $ids );
	}
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'tlharris_exclude_reserved_from_sitemap' );

/**
 * Users are not public content on this site.
 */
function tlharris_remove_users_sitemap( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'tlharris_remove_users_sitemap', 10, 2 );

/* -------------------------------------------------------------------------
 * Activation
 * ---------------------------------------------------------------------- */

function tlharris_create_reserved_story_page() {
	$existing = (int) get_option( 'tlharris_my_story_page_id', 0 );
	if ( $existing && get_post( $existing ) ) {
		return;
	}

	$page = get_page_by_path( 'my-story' );

	if ( ! $page ) {
		$page_id = wp_insert_post(
			array(
				'post_title'   => 'My Story',
				'post_name'    => 'my-story',
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_content' => '',
			)
		);

		if ( is_wp_error( $page_id ) ) {
			return;
		}
	} else {
		$page_id = $page->ID;
	}

	update_option( 'tlharris_my_story_page_id', (int) $page_id );
}

register_activation_hook(
	__FILE__,
	function () {
		tlharris_register_post_types();
		tlharris_register_taxonomies();
		tlharris_seed_terms();
		tlharris_create_reserved_story_page();

		if ( ! get_option( 'tlharris_identity' ) ) {
			update_option( 'tlharris_identity', tlharris_identity_defaults() );
		}

		update_option( 'tlharris_core_version', TLHARRIS_CORE_VERSION );
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/**
 * Rewrite rules also need flushing when the plugin is updated in place, not
 * only when it is activated through the admin screen.
 */
function tlharris_maybe_flush_rewrites() {
	if ( get_option( 'tlharris_core_version' ) !== TLHARRIS_CORE_VERSION ) {
		update_option( 'tlharris_core_version', TLHARRIS_CORE_VERSION );
		flush_rewrite_rules();
	}
}
add_action( 'init', 'tlharris_maybe_flush_rewrites', 99 );
