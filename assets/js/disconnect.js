/*
 * PPPoE disconnect button.
 *
 * This file used to contain raw <script> tags, including one pulling in
 * jQuery. A .js file is parsed as JavaScript, not HTML, so the browser
 * threw "Unexpected token '<'" on the first character and none of it
 * ran. Nothing in the application references it today; it is left here
 * as valid JavaScript so that wiring it up actually works.
 *
 * Requires jQuery on the page, and includes/header.php, whose CSRF shim
 * attaches the token to the POST below.
 */

$('.disconnect-btn').on('click', function () {
    if (!confirm('Disconnect this PPPoE user?')) { return; }

    var username = $(this).data('username');
    var btn = $(this);

    $.post('disconnect_user.php', { username: username }, function (data) {
        var res = (typeof data === 'string') ? JSON.parse(data) : data;
        if (res.success) {
            $('#status-' + username)
                .text('Offline')
                .removeClass('badge active')
                .addClass('badge expired');
            btn.remove();
            $('#msg-box').text(res.msg).css('color', '#2ecc71').fadeIn().delay(2000).fadeOut();
        } else {
            $('#msg-box').text(res.msg).css('color', '#e74c3c').fadeIn().delay(4000).fadeOut();
        }
    });
});
