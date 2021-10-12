<?php
/**
 * User details.
 *
 * @package wpcd
 */

if ( empty( $server_posts ) && empty( $app_posts ) ) {
	?>
	<p><?php echo esc_html( __( 'This user is not assigned to any server or app.', 'wpcd' ) ); ?></p>
	<?php
}

if ( ! empty( $server_posts ) || ! empty( $app_posts ) ) {
	?>
	<h2><?php echo esc_html( __( 'List of Servers or Apps that the user can view', 'wpcd' ) ); ?></h2>
	<?php
}

// Shows the servers that the user can view.
if ( $server_posts ) {
	?>
	<h4><?php echo esc_html( __( 'Servers', 'wpcd' ) ); ?></h4>
	<ol>
		<?php
		foreach ( $server_posts as $server_post ) {
			?>
				<li>
				<?php
					$server_title = get_the_title( $server_post );
					$url          = admin_url( sprintf( 'post.php?post=%s&action=edit', $server_post ) );
					$server_link  = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $url ), esc_html( $server_title ) );
					echo $server_link;
				?>
				</li>
				<?php
		}
		?>
	</ol>
	<?php
}

// Shows the apps that the user can view.
if ( $app_posts ) {
	?>
	<h4><?php echo esc_html( __( 'Apps', 'wpcd' ) ); ?></h4>
	<ol>
		<?php
		foreach ( $app_posts as $app_post ) {
			?>
				<li>
				<?php
					$app_title = get_the_title( $app_post );
					$url       = admin_url( sprintf( 'post.php?post=%s&action=edit', $app_post ) );
					$app_link  = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $url ), esc_html( $app_title ) );
					echo $app_link;
				?>
				</li>
				<?php
		}
		?>
	</ol>
	<?php
}
