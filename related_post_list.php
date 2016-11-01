<?php
	if ( !empty( $related_posts )):
		$posts = array(
			'media' => array(),
			'none' => array(),
		);

		foreach ( $related_posts as $post ) {
			if ( !empty( $post->thumbnail_id )) {
				$posts['media'][] = $post;
			} else {
				$posts['none'][] = $post;
			}
		}
?>

<div class="related-posts">
	<h3><?php echo __( 'Related Posts', 'related-posts' ) ?></h3>

	<?php if ( !empty( $posts['media'])): ?>
	<div class="related-posts-with-media">
		<?php foreach ( $posts['media'] as $post ): ?>
		<div class="related-post">
			<?php echo get_the_post_thumbnail( $post, 'related-thumb' ); ?>
			<h4><a href="<?php echo get_permalink( $post ); ?>"><?php echo $post->post_title; ?></a></h4>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if ( !empty( $posts['none'])): ?>
	<div class="related-posts-no-media">
		<?php foreach ( $posts['none'] as $post ): ?>
		<div class="related-post">
			<h4><a href="<?php echo get_permalink( $post ); ?>"><?php echo $post->post_title; ?></a></h4>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</div>

<?php endif;
