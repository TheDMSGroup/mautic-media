Mautic.mediaAuthorization = function () {
    var $form = mQuery('form[name="media"]:first'),
        $provider = $form.find('select[name="media[provider]"]:first'),
        provider = $provider.val(),
        $accountId = $form.find('input[name="media[account_id]"]:first'),
        $clientId = $form.find('input[name="media[client_id]"]:first'),
        $clientSecret = $form.find('input[name="media[client_secret]"]:first'),
        $token = $form.find('input[name="media[token]"]:first'),
        $refreshToken = $form.find('input[name="media[refresh_token]"]:first'),
        authCookieVal = mQuery.cookie('mauticMediaAuthChange'),
        authCookieLast = null,
        authCheck = null,
        authCheckFunction = function () {
            authCookieVal = mQuery.cookie('mauticMediaAuthChange');
            if (typeof authCookieVal == 'string'
                && authCookieVal.length
                && authCookieVal !== authCookieLast
            ) {
                clearInterval(authCheck);
                authCookieLast = authCookieVal;
                mQuery.ajax({
                    url: mauticAjaxUrl,
                    type: 'POST',
                    data: {
                        action: 'plugin:mauticMedia:getAuthTokens',
                        provider: provider,
                        mediaAccountId: Mautic.getEntityId(),
                    },
                    dataType: 'json',
                    cache: false,
                    success: function (response) {
                        var success = false;
                        if (typeof response.success !== 'undefined' && response.success) {
                            if (typeof response.token !== 'undefined' && response.token) {
                                $token.val(response.token);
                                success = true;
                            }
                            if (typeof response.refreshToken !== 'undefined' && response.refreshToken) {
                                $refreshToken.val(response.refreshToken);
                                success = true;
                            }
                        }
                        if (!success) {
                            authCheck = setInterval(authCheckFunction, 250);
                        }
                    },
                    error: function (request, textStatus, errorThrown) {
                        Mautic.processAjaxError(request, textStatus, errorThrown);
                    }
                });
            }
        };

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
            if (typeof response.success !== 'undefined' && response.success) {
                if (typeof response.authUri !== 'undefined' && response.authUri) {
                    var newwindow = window.open(response.authUri, '_blank', 'height=640,width=480');
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
            if (authCheck) {
                clearInterval(authCheck);
            }
            authCheck = setInterval(authCheckFunction, 250);
        }
    });
};