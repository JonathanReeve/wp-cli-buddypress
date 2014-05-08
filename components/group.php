<?php

/**
 * Manage BuddyPress groups.
 */
class BPCLI_Group extends BPCLI_Component {

	/**
	 * Create a group.
	 *
	 * ## OPTIONS
	 *
	 * --name=<name>
	 * : Name of the group.
	 *
	 * [--slug=<slug>]
	 * : URL-safe slug for the group. If not provided, one will be generated automatically.
	 *
	 * [--description=<description>]
	 * : Group description. Default: 'Description for group "[name]"'
	 *
	 * [--creator-id=<creator-id>]
	 * : ID of the group creator. Default: 1.
	 *
	 * [--slug=<slug>]
	 * : URL-safe slug for the group.
	 *
	 * [--status=<status>]
	 * : Group status (public, private, hidden). Default: public.
	 *
	 * [--enable-forum=<enable-forum>]
	 * : Whether to enable legacy bbPress forums. Default: 0.
	 *
	 * [--date-created=<date-created>]
	 * : MySQL-formatted date. Default: current date.
	 *
	 * ## EXAMPLES
	 *
	 *        wp bp group create --name="Totally Cool Group"
	 *        wp bp group create --name="Sports" --description="People who love sports" --creator-id=54 --status=private
	 *
	 * @synopsis --name=<name> [--slug=<slug>] [--description=<description>] [--creator-id=<creator-id>] [--status=<status>] [--enable-forum=<enable-forum>] [--date-created=<date-created>]
	 *
	 * @since 1.0
	 */
	public function create( $args, $assoc_args ) {
		$r = wp_parse_args( $assoc_args, array(
			'name' => '',
			'slug' => '',
			'description' => '',
			'creator_id' => 1,
			'status' => 'hidden',
			'enable_forum' => 0,
			'date_created' => bp_core_current_time(),
		) );

		if ( ! $r['name'] ) {
			WP_CLI::error( 'You must provide a --name parameter when creating a group.' );
		}

		// Auto-generate some stuff
		if ( ! $r['slug'] ) {
			$r['slug'] = groups_check_slug( sanitize_title( $r['name'] ) );
		}

		// Make a URL like http://commons.mla.org/groups/prospective-forum-tc-ecocriticism-and-environmental-humanities/forum/topic/petition/
		$group_slug = $r['slug']; 
		$petition_url =  site_url() . "/groups/$group_slug/forum/topic/petition/"; 

		// MLA Descriptions 
		$r['description'] = 'On this MLA Commons group, you can petition for a new forum preliminarily authorized by the Executive Council as part of the association’s restructuring of division and discussion groups. To learn more about this initiative, please <a href="">read a letter from Margaret W. Ferguson and Marianne Hirsch</a>. To petition for the creation of this new forum, you may add your name as a comment in the <a href="' . $petition_url . '">Petition thread</a> of the forum discussion. (Please note that members may sign no more than five new forum petitions during the 2014 membership year.) Additional information about establishing new forums and the full list of new forums are available in the <a href="">Instructions for Establishing New Forums</a>.'; 

		if ( ! $r['description'] ) {
			$r['description'] = sprintf( 'Description for group "%s"', $r['name'] );
		}

		//debugging
		//WP_CLI::success( "Group description: ".$r['description']);
		//exit(); 

		if ( $id = groups_create_group( $r ) ) {
			groups_update_groupmeta( $id, 'total_member_count', 1 );
			groups_update_groupmeta( $id, 'mla_oid', 'FXX' ); 
			$group = groups_get_group( array( 'group_id' => $id ) );
			WP_CLI::success( "Group $id created: " . bp_get_group_permalink( $group ) );
		} else {
			WP_CLI::error( 'Could not create group.' );
		}

	}

	/**
	 * Add a member to a group.
	 *
	 * ## OPTIONS
	 *
	 * --group-id=<group>
	 * : Identifier for the group. Accepts either a slug or a numeric ID.
	 *
	 * --user-id=<user>
	 * : Identifier for the user. Accepts either a user_login or a numeric ID.
	 *
	 * [--role=<role>]
	 * : Group role for the new member (member, mod, admin). Default: member.
	 *
	 * ## EXAMPLES
	 *
	 *        wp bp group add_member --group-id=3 --user-id=10
	 *        wp bp group add_member --group-id=foo --user-id=admin role=mod
	 *
	 * @synopsis --group-id=<group> --user-id=<user> [--role=<role>]
	 *
	 * @since 1.0
	 */
	public function add_member( $args, $assoc_args ) {
		$r = wp_parse_args( $assoc_args, array(
			'group-id' => null,
			'user-id' => null,
			'role' => 'member',
		) );

		// Convert --group_id to group ID
		// @todo this'll be screwed up if the group has a numeric slug
		if ( ! is_numeric( $r['group-id'] ) ) {
			$group_id = groups_get_id( $r['group-id'] );
		} else {
			$group_id = $r['group-id'];
		}

		// Check that group exists
		$group_obj = groups_get_group( array( 'group_id' => $group_id ) );
		if ( empty( $group_obj->id ) ) {
			WP_CLI::error( 'No group found by that slug or id.' );
		}

		$user_id = $this->get_user_id_from_identifier( $r['user-id'] );

		if ( empty( $user_id ) ) {
			WP_CLI::error( 'No user found by that username or id' );
		}

		// Sanitize role
		if ( ! in_array( $r['role'], array( 'member', 'mod', 'admin' ) ) ) {
			$r['role'] = 'member';
		}

		$joined = groups_join_group( $group_id, $user_id );

		if ( $joined ) {
			if ( 'member' !== $r['role'] ) {
				$the_member = new BP_Groups_Member( $user_id, $group_id );
				$member->promote( $r['role'] );
			}

			$success = sprintf(
				'Added user #%d (%s) to group #%d (%s) as %s',
				$user_id,
				$user_obj->user_login,
				$group_id,
				$group_obj->name,
				$r['role']
			);
			WP_CLI::success( $success );
		} else {
			WP_CLI::error( 'Could not add user to group.' );
		}
	}
}

WP_CLI::add_command( 'bp group', 'BPCLI_Group', array(
	'before_invoke' => function() {
		if ( ! bp_is_active( 'group' ) ) {
//			WP_CLI::error( 'The Group component is not active.' );
		}
} ) );

