<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$('.disconnect-btn').on('click', function(){
    if(!confirm('Disconnect this PPPoE user?')) return;

    var username = $(this).data('username');
    var btn = $(this);

    $.post('disconnect_user.php', {username: username}, function(data){
        var res = JSON.parse(data);
        if(res.success){
            $('#status-' + username)
                .text('Offline')
                .removeClass('badge active')
                .addClass('badge expired');
            btn.remove(); // remove button
            $('#msg-box').text(res.msg).css('color','#2ecc71').fadeIn().delay(2000).fadeOut();
        } else {
            $('#msg-box').text(res.msg).css('color','#e74c3c').fadeIn().delay(4000).fadeOut();
        }
    });
});
</script>
