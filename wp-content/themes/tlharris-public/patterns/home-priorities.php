<?php
/**
 * Title: Homepage — priorities
 * Slug: tlharris-public/home-priorities
 * Categories: tlharris
 * Inserter: no
 * Description: The most recent tracked priorities, with their status.
 */
?>
<!-- wp:group {"className":"tlharris-split tlharris-keyline","layout":{"type":"default"}} -->
<div class="wp-block-group tlharris-split tlharris-keyline">
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">
<!-- wp:heading -->
<h2 class="wp-block-heading">My goals for District 6</h2>
<!-- /wp:heading -->
</div>
<!-- /wp:group -->
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"className":"tlharris-lede"} -->
<p class="tlharris-lede">I ran on early reading, safe schools, transparency and parent engagement. Progress on each is reported at the start of every quarter.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:query {"query":{"perPage":4,"pages":0,"offset":0,"postType":"priority","order":"desc","orderBy":"date","search":"","exclude":[],"sticky":"","inherit":false},"layout":{"type":"constrained"},"className":"tlharris-query-current-work"} -->
<div class="wp-block-query tlharris-query-current-work">
<!-- wp:post-template {"layout":{"type":"grid","columnCount":4}} -->
<!-- wp:group {"className":"tlharris-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group tlharris-card">
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-excerpt /-->
</div>
<!-- /wp:group -->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>Priorities will appear here as their tracking records are published on this site.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->

<!-- wp:paragraph -->
<p><a href="/priorities-progress/">See all eight priorities and the quarterly reports</a></p>
<!-- /wp:paragraph -->
