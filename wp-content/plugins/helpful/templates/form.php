<?php if(!Helpful::hasVisitorRated(get_the_ID())): ?>
<form action="<?php echo Helpful::getFormUrl(); ?>" method="post" id="helpful-rating">
    <div>
        <h3>Was this post helpful for you?</h3>
    </div>
    <label class="rating-1"><input type="radio" name="rating" value="1">Useless!</label>
    <label class="rating-3"><input type="radio" name="rating" value="3">It's okay i guess...</label>
    <label class="rating-5"><input type="radio" name="rating" value="5">It was awesome!</label>
    <div class="why">
        <label for="why">Why?</label>
        <textarea name="why" id="why" cols="30" rows="10" placeholder="Could you provide me some feedback?"></textarea>
    </div>
    <input type="hidden" name="helpful-rating" value="1" />
    <input type="hidden" name="post_id" value="<?php echo the_ID(); ?>" />
    <input type="submit" value="Send feedback">
    <?php wp_nonce_field( 'helpful' ); ?>
</form>
<?php endif; ?>
