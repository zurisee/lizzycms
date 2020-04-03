// LiveData

var ajaxHndl = null;
var lastUpdated = 0;
var polltime = 60;
var refs = '';
var debugOutput = false;


function initLiveData() {
    // collect all references within page:
    $('[data-live-data-ref]').each(function () {
        var ref = $( this ).attr('data-live-data-ref');
        refs = refs + ref + ',';
    });
    refs = refs.replace(/,+$/, '');

    // check for polltime argument: (only apply first appearance)
    $('[data-live-data-polltime]').each(function () {
        polltime = parseInt($( this ).attr('data-live-data-polltime'));
        console.log('custom polltime: ' + polltime + 's');
        return;
    });

    debugOutput = ($('.debug').length !== 0);

    updateLiveData( true );
} // init



function updateDOM(data) {
    for (var id in data.data) {
        var val = data.data[id];
        $('#' + id).text(val);
        if (debugOutput) {
            console.log(id + ' -> ' + val);
        }
    }
}



function updateLiveData( forceUpdate ) {
    var url = appRoot + "_lizzy/_live_data_service.php";
    if (typeof forceUpdate !== 'undefined') {
        url = url + '?forceUpdate';
    }

    ajaxHndl = $.ajax({
        url: url,
        type: 'POST',
        data: { ref: refs, last: lastUpdated, polltime: polltime },
        async: true,
        cache: false,
    }).done(function ( json ) {
        if (!json) {
            console.log('No data received');
            return;
        }
        var data = JSON.parse(json);
        if (typeof data.lastUpdated !== 'undefined') {
            lastUpdated = data.lastUpdated;
        }
        if (typeof data.result === 'undefined') {
            console.log('_live_data_service.php reported an error');
            console.log(json);
            return;
        }

        // regular response:
        if (typeof data.data === 'undefined') {
            if (debugOutput) {
                console.log( timeStamp() + ': No new data');
            }
        } else {
            var goOn = true;
            if (typeof liveDataCallback === 'function') {
                goOn = liveDataCallback( data );
            }
            if (goOn) {
                updateDOM( data );
            }
        }
        $('.live-data-update-time').text( timeStamp() );
        updateLiveData();
    });
} // update


// initialize live data:
$( document ).ready(function() {
    if ($('[data-live-data-ref]').length) {
        console.log('starting live-data');
        setTimeout(function () {
            initLiveData();
        }, 2);
    }
});

