// Key page: copy the new key and leave the address of the POST request. A file, since xTIPP sends a
// Content-Security-Policy, which blocks inline scripts and event attributes.
(function () {
    var box = document.getElementById('rest-api-new-key-box');
    if (box === null) {
        return;
    }

    // The page is the answer of a POST request: a reload must show the key page (without the key) and must not send
    // the form again
    var pageUrl = box.getAttribute('data-page-url');
    if (pageUrl && window.history && window.history.replaceState) {
        window.history.replaceState(null, '', pageUrl);
    }

    var input  = document.getElementById('rest-api-new-key');
    var button = document.getElementById('rest-api-copy-key');

    input.addEventListener('focus', function () {
        input.select();
    });

    button.addEventListener('click', function () {
        input.select();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(input.value);
        }
    });
})();
