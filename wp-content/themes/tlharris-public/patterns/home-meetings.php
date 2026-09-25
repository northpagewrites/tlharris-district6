<?php
/**
 * Title: Homepage — upcoming meetings
 * Slug: tlharris-public/home-meetings
 * Categories: tlharris
 * Inserter: no
 * Description: Next public meetings from the event records.
 */
?>
<!-- wp:group {"className":"tlharris-split tlharris-keyline","layout":{"type":"default"}} -->
<div class="wp-block-group tlharris-split tlharris-keyline">
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">
<!-- wp:heading -->
<h2 class="wp-block-heading">On the calendar</h2>
<!-- /wp:heading -->
</div>
<!-- /wp:group -->
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"className":"tlharris-lede"} -->
<p class="tlharris-lede">Board meetings are open to the public and streamed live. Public comment is taken at business meetings.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:query {"query":{"perPage":3,"pages":0,"offset":0,"postType":"event","order":"desc","orderBy":"date","search":"","exclude":[],"sticky":"","inherit":false},"layout":{"type":"constrained"},"className":"tlharris-query-upcoming-events"} -->
<div class="wp-block-query tlharris-query-upcoming-events">
<!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:group {"className":"tlharris-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group tlharris-card">
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"tlharris/date","args":{"key":"event_date"}}}},"className":"tlharris-meta"} -->
<p class="tlharris-meta"></p>
<!-- /wp:paragraph -->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tlharris_event_time"}}}},"className":"tlharris-meta"} -->
<p class="tlharris-meta"></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tlharris_event_location"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>No upcoming meetings are confirmed right now.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->

<!-- wp:paragraph -->
<p><a href="/board-work/">Full meeting schedule and how to speak at a meeting</a></p>
<!-- /wp:paragraph -->
