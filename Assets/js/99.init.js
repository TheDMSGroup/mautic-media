// General helpers for the Contact Client editor form.
Mautic.mediaOnLoad = function () {
    Mautic.mediaCampaigns();

    // Hide the right column when Campaigns tab is open to give more room
    // for table entry.
    var activeTab = '#details';
    mQuery('.media-tab').click(function () {
        var thisTab = mQuery(this).attr('href');
        if (thisTab !== activeTab) {
            activeTab = thisTab;
            if (activeTab === '#campaigns') {
                // Expanded view.
                mQuery('.media-left').addClass('col-md-12').removeClass('col-md-9');
                mQuery('.media-right').addClass('hide');
            }
            else {
                // Standard view.
                mQuery('.media-left').removeClass('col-md-12').addClass('col-md-9');
                mQuery('.media-right').removeClass('hide');
            }
        }
    });
};