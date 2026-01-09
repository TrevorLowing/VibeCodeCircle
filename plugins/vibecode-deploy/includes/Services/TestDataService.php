<?php
/**
 * Test Data Service
 *
 * Creates example posts for all registered CPTs to help with testing and development.
 *
 * @package VibeCode\Deploy
 */

namespace VibeCode\Deploy\Services;

/**
 * Test Data Service
 */
class TestDataService {

	/**
	 * Seed test data for all CPTs.
	 *
	 * @param array $selected_cpts Optional array of CPT slugs to seed. If empty, seeds all CPTs.
	 * @return array Results with 'created', 'skipped', 'errors' keys.
	 */
	public static function seed_test_data( array $selected_cpts = array() ): array {
		$results = array(
			'created' => array(),
			'skipped' => array(),
			'errors' => array(),
		);

		$cpts_to_seed = array(
			'advisory',
			'investigation',
			'evidence_record',
			'foia_request',
			'foia_update',
			'survey',
		);

		// Filter to selected CPTs if provided
		if ( ! empty( $selected_cpts ) ) {
			$cpts_to_seed = array_intersect( $cpts_to_seed, $selected_cpts );
		}

		foreach ( $cpts_to_seed as $cpt ) {
			if ( ! post_type_exists( $cpt ) ) {
				$results['skipped'][] = array(
					'cpt' => $cpt,
					'reason' => 'CPT not registered',
				);
				continue;
			}

			// Check if CPT already has posts
			$existing = wp_count_posts( $cpt );
			if ( (int) $existing->publish > 0 ) {
				$results['skipped'][] = array(
					'cpt' => $cpt,
					'reason' => 'CPT already has published posts',
				);
				continue;
			}

			$method = 'seed_' . $cpt;
			if ( method_exists( self::class, $method ) ) {
				try {
					$created = self::$method();
					$results['created'][ $cpt ] = $created;
				} catch ( \Exception $e ) {
					$results['errors'][] = array(
						'cpt' => $cpt,
						'error' => $e->getMessage(),
					);
				}
			} else {
				$results['skipped'][] = array(
					'cpt' => $cpt,
					'reason' => 'No seed method available',
				);
			}
		}

		return $results;
	}

	/**
	 * Seed advisory posts.
	 *
	 * @return array Created post IDs.
	 */
	private static function seed_advisory(): array {
		$advisories = array(
			array(
				'title' => 'Management Advisory 25-A04: Agency Procurement Practices',
				'docket_id' => '25-A04',
				'classification' => 'Management Advisory',
				'anchor_id' => 'advisory-25-a04',
				'date' => '2024-11-15',
				'teaser' => 'This advisory examines procurement practices across federal agencies and identifies systemic issues with vendor selection and contract management.',
				'executive_summary' => 'Our analysis of procurement data reveals significant inconsistencies in vendor selection criteria and contract oversight mechanisms.',
			),
			array(
				'title' => 'Legislative Referral 25-L12: Data Transparency Requirements',
				'docket_id' => '25-L12',
				'classification' => 'Legislative Referral',
				'anchor_id' => 'advisory-25-l12',
				'date' => '2024-12-01',
				'teaser' => 'A comprehensive review of data transparency requirements and their implementation across multiple federal agencies.',
				'executive_summary' => 'This referral addresses gaps in data transparency requirements and recommends legislative action to strengthen public access to government information.',
			),
			array(
				'title' => 'Market Warning 25-M08: Cybersecurity Vendor Risks',
				'docket_id' => '25-M08',
				'classification' => 'Market Warning',
				'anchor_id' => 'advisory-25-m08',
				'date' => '2024-10-20',
				'teaser' => 'Warning about cybersecurity vendors with inadequate security practices and potential risks to federal systems.',
				'executive_summary' => 'Our investigation has identified several cybersecurity vendors with significant security vulnerabilities that pose risks to federal information systems.',
			),
		);

		$created = array();
		foreach ( $advisories as $data ) {
			$post_id = wp_insert_post(
				array(
					'post_title' => $data['title'],
					'post_content' => '<p>This is a test advisory post created by the Vibe Code Deploy test data seeder.</p>',
					'post_status' => 'publish',
					'post_type' => 'advisory',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, 'cfa_advisory_docket_id', $data['docket_id'] );
			update_post_meta( $post_id, 'cfa_advisory_classification', $data['classification'] );
			update_post_meta( $post_id, 'cfa_advisory_anchor_id', $data['anchor_id'] );
			update_post_meta( $post_id, 'cfa_advisory_date', $data['date'] );
			update_post_meta( $post_id, 'cfa_advisory_teaser', $data['teaser'] );
			update_post_meta( $post_id, 'cfa_advisory_executive_summary', $data['executive_summary'] );

			$created[] = $post_id;
		}

		return $created;
	}

	/**
	 * Seed investigation posts.
	 *
	 * @return array Created post IDs.
	 */
	private static function seed_investigation(): array {
		// Check if investigations already exist
		$existing = wp_count_posts( 'investigation' );
		if ( (int) $existing->publish > 0 ) {
			return array(); // Skip if already has posts
		}

		$investigations = array(
			array(
				'title' => 'Investigation 25-001: Agency Contract Oversight',
				'docket_id' => '25-001',
				'status' => 'Open',
				'visibility' => 'public',
				'hypothesis' => 'Federal agencies are not adequately overseeing contractor performance and compliance.',
				'methodology' => '<p>This investigation analyzes procurement data from multiple federal agencies to identify patterns in contractor oversight. We are reviewing contract award documentation, performance evaluations, and compliance reports to assess the effectiveness of oversight mechanisms.</p><p>Our methodology includes FOIA requests for contract files, analysis of publicly available procurement databases, and interviews with agency procurement officials where possible.</p>',
				'latest_update' => 'Initial data collection phase completed. Reviewing contract files from Department of Commerce and Department of Defense.',
				'last_updated' => '2024-12-15',
			),
			array(
				'title' => 'Investigation 25-002: Data Retention Policies',
				'docket_id' => '25-002',
				'status' => 'In Analysis',
				'visibility' => 'public',
				'hypothesis' => 'Agencies are not consistently following data retention policies, leading to premature data deletion.',
				'methodology' => '<p>This investigation examines agency compliance with federal records management requirements. We are reviewing data retention schedules, records disposition schedules, and actual data deletion practices across multiple agencies.</p><p>Our approach includes document analysis, interviews with records management officers, and review of FOIA request responses to identify instances where requested records were deleted prematurely.</p>',
				'latest_update' => 'Analysis phase in progress. Reviewing records management policies from 15 federal agencies.',
				'last_updated' => '2024-12-10',
			),
		);

		$created = array();
		foreach ( $investigations as $data ) {
			$post_id = wp_insert_post(
				array(
					'post_title' => $data['title'],
					'post_content' => $data['methodology'],
					'post_status' => 'publish',
					'post_type' => 'investigation',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, 'cfa_investigation_docket_id', $data['docket_id'] );
			update_post_meta( $post_id, 'cfa_investigation_status', $data['status'] );
			update_post_meta( $post_id, 'cfa_investigation_visibility', $data['visibility'] );
			update_post_meta( $post_id, 'cfa_investigation_hypothesis', $data['hypothesis'] );
			update_post_meta( $post_id, 'cfa_investigation_latest_update', $data['latest_update'] );
			update_post_meta( $post_id, 'cfa_investigation_last_updated', $data['last_updated'] );

			$created[] = $post_id;
		}

		return $created;
	}

	/**
	 * Seed evidence_record posts.
	 *
	 * @return array Created post IDs.
	 */
	private static function seed_evidence_record(): array {
		$records = array(
			array(
				'title' => 'FOIA Response: Agency Procurement Records',
				'type' => 'FOIA',
				'source_agency' => 'Department of Commerce',
				'date_received' => '2024-11-20',
				'reference_id' => 'FOIA-2024-001234',
				'source_url' => 'https://example.com/foia/001234',
			),
			array(
				'title' => 'OIG Report: Contract Management Review',
				'type' => 'OIG Report',
				'source_agency' => 'Department of Defense',
				'date_received' => '2024-10-15',
				'reference_id' => 'OIG-2024-5678',
				'source_url' => 'https://example.com/oig/5678',
			),
			array(
				'title' => 'GAO Report: Federal IT Security',
				'type' => 'GAO Report',
				'source_agency' => 'Government Accountability Office',
				'date_received' => '2024-09-30',
				'reference_id' => 'GAO-24-12345',
				'source_url' => 'https://example.com/gao/12345',
			),
		);

		$created = array();
		foreach ( $records as $data ) {
			$post_id = wp_insert_post(
				array(
					'post_title' => $data['title'],
					'post_content' => '<p>This is a test evidence record created by the Vibe Code Deploy test data seeder.</p>',
					'post_status' => 'publish',
					'post_type' => 'evidence_record',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, 'cfa_evidence_record_type', $data['type'] );
			update_post_meta( $post_id, 'cfa_evidence_record_source_agency', $data['source_agency'] );
			update_post_meta( $post_id, 'cfa_evidence_record_date_received', $data['date_received'] );
			update_post_meta( $post_id, 'cfa_evidence_record_reference_id', $data['reference_id'] );
			update_post_meta( $post_id, 'cfa_evidence_record_source_url', $data['source_url'] );

			$created[] = $post_id;
		}

		return $created;
	}

	/**
	 * Seed foia_request posts.
	 *
	 * @return array Created post IDs.
	 */
	private static function seed_foia_request(): array {
		// Check if FOIA requests already exist
		$existing = wp_count_posts( 'foia_request' );
		if ( (int) $existing->publish > 0 ) {
			return array(); // Skip if already has posts
		}

		$requests = array(
			array(
				'title' => 'FOIA Request: Agency Procurement Records',
				'visibility' => 'public',
				'status' => 'Fulfilled',
				'submitted_at' => '2024-09-01',
				'due_at' => '2024-10-01',
				'closed_at' => '2024-09-25',
				'fees_incurred' => '0',
				'cooperation_score' => '5',
			),
			array(
				'title' => 'FOIA Request: Contract Award Documentation',
				'visibility' => 'public',
				'status' => 'In Progress',
				'submitted_at' => '2024-11-15',
				'due_at' => '2024-12-15',
				'fees_incurred' => '',
				'cooperation_score' => '',
			),
		);

		$created = array();
		foreach ( $requests as $data ) {
			$post_id = wp_insert_post(
				array(
					'post_title' => $data['title'],
					'post_content' => '<p>This is a test FOIA request created by the Vibe Code Deploy test data seeder.</p>',
					'post_status' => 'publish',
					'post_type' => 'foia_request',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, 'cfa_foia_visibility', $data['visibility'] );
			update_post_meta( $post_id, 'cfa_foia_status', $data['status'] );
			update_post_meta( $post_id, 'cfa_foia_submitted_at', $data['submitted_at'] );
			if ( ! empty( $data['due_at'] ) ) {
				update_post_meta( $post_id, 'cfa_foia_due_at', $data['due_at'] );
			}
			if ( ! empty( $data['closed_at'] ) ) {
				update_post_meta( $post_id, 'cfa_foia_closed_at', $data['closed_at'] );
			}
			if ( ! empty( $data['fees_incurred'] ) ) {
				update_post_meta( $post_id, 'cfa_foia_fees_incurred', $data['fees_incurred'] );
			}
			if ( ! empty( $data['cooperation_score'] ) ) {
				update_post_meta( $post_id, 'cfa_foia_cooperation_score', $data['cooperation_score'] );
			}

			$created[] = $post_id;
		}

		return $created;
	}

	/**
	 * Seed foia_update posts.
	 *
	 * @return array Created post IDs.
	 */
	private static function seed_foia_update(): array {
		// Check if FOIA updates already exist
		$existing = wp_count_posts( 'foia_update' );
		if ( (int) $existing->publish > 0 ) {
			return array(); // Skip if already has posts
		}

		$updates = array(
			array(
				'title' => 'Update: FOIA Request Acknowledged',
				'date' => '2024-11-20',
			),
			array(
				'title' => 'Update: Partial Response Received',
				'date' => '2024-11-25',
			),
		);

		$created = array();
		foreach ( $updates as $data ) {
			$post_id = wp_insert_post(
				array(
					'post_title' => $data['title'],
					'post_content' => '<p>This is a test FOIA update created by the Vibe Code Deploy test data seeder.</p>',
					'post_status' => 'publish',
					'post_type' => 'foia_update',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			$created[] = $post_id;
		}

		return $created;
	}

	/**
	 * Seed survey posts.
	 *
	 * @return array Created post IDs.
	 */
	private static function seed_survey(): array {
		$today = (int) gmdate( 'Ymd' );
		$start_date = $today;
		$end_date = $today + 30; // 30 days from now

		$surveys = array(
			array(
				'title' => 'Federal Agency Transparency Survey',
				'link' => 'https://example.com/survey/transparency',
				'start_date' => (string) $start_date,
				'end_date' => (string) $end_date,
				'active' => '1',
			),
			array(
				'title' => 'Contractor Performance Survey',
				'link' => 'https://example.com/survey/contractors',
				'start_date' => (string) $start_date,
				'end_date' => (string) $end_date,
				'active' => '1',
			),
		);

		$created = array();
		foreach ( $surveys as $data ) {
			$post_id = wp_insert_post(
				array(
					'post_title' => $data['title'],
					'post_content' => '',
					'post_status' => 'publish',
					'post_type' => 'survey',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, 'cfa_survey_link', $data['link'] );
			update_post_meta( $post_id, 'cfa_survey_start_date', $data['start_date'] );
			update_post_meta( $post_id, 'cfa_survey_end_date', $data['end_date'] );
			update_post_meta( $post_id, 'cfa_survey_active', $data['active'] );

			$created[] = $post_id;
		}

		return $created;
	}
}
