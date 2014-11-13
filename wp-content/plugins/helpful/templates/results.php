<?php if(Helpful::hasRating(get_the_ID())): ?>
<div id="helpful-result">
    <div>
        <h3>Rating</h3>
    </div>
    <p>
        Visitors give this article an average rating of <strong><?php 
            echo number_format(Helpful::getRating(get_the_ID()), 1); ?></strong> out of 5.
    </p>
    
</div>
<?php endif; ?>