Mautic.mediaAuthorization = function () {
    var $form = mQuery('form[name="media"]:first'),
        $provider = $form.find('select[name="media[provider]"]:first'),
        provider = $provider.val(),
        $accountId = $form.find('input[name="media[account_id]"]:first'),
        $clientId = $form.find('input[name="media[client_id]"]:first'),
        $clientSecret = $form.find('input[name="media[client_secret]"]:first'),
        $token = $form.find('input[name="media[token]"]:first'),
        $refreshToken = $form.find('input[name="media[refresh_token]"]:first');

    mQuery.ajax({
        url: mauticAjaxUrl,
        type: 'POST',
        data: {
            action: 'plugin:mauticMedia:startAuth',
            mediaAccountId: Mautic.getEntityId(),
            provider: provider,
            accountId: $accountId.val(),
            clientId: $clientId.val(),
            clientSecret: $clientSecret.val(),
            token: $token.val(),
            refreshToken: $refreshToken.val(),
        },
        dataType: 'json',
        cache: false,
        success: function (response) {
            console.log(response);
            if (typeof response.success !== 'undefined' && response.success) {
                if (typeof response.authUri !== 'undefined' && response.authUri) {
                    var newwindow = window.open(response.authUri, '_blank', 'height=200,width=200');
                    if (window.focus) {
                        newwindow.focus();
                    }
                }
            }
        },
        error: function (request, textStatus, errorThrown) {
            Mautic.processAjaxError(request, textStatus, errorThrown);
        },
        complete: function () {
            mQuery('#media_authButton .fa').addClass('fa-key').removeClass('fa-spin').removeClass('fa-spinner');
        }
    });
};