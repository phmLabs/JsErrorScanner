var cookieMaker;
var mainDomain;

function extractDomain(url) {
    var domain;
    //find & remove protocol (http, ftp, etc.) and get domain
    if (url.indexOf("://") > -1) {
        domain = url.split('/')[2];
    }
    else {
        domain = url.split('/')[0];
    }

    //find & remove port number
    domain = domain.split(':')[0];

    return domain;
}

chrome.webRequest.onBeforeSendHeaders.addListener(
    function (details) {
        if (details.url.indexOf(mainDomain) > 0) {
            cookieSet = false;
            for (var i = 0; i < details.requestHeaders.length; ++i) {
                if (details.requestHeaders[i].name.toLowerCase() === 'cookie') {
                    details.requestHeaders[i].value = details.requestHeaders[i].value + cookieMaker;
                    cookieSet = true;
                    break;
                }
            }

            if (!cookieSet) {
                details.requestHeaders.push({name: 'cookie', value: cookieMaker});
            }
        }

        return {requestHeaders: details.requestHeaders};
    },
    {urls: ['<all_urls>']},
    ['blocking', 'requestHeaders']
);

chrome.webRequest.onBeforeRequest.addListener(
    function (info) {
        if (!cookieMaker) {
            if (info.url.indexOf('#cookie=') > 0) {
                mainDomain = extractDomain(info.url);
                cookieMaker = '; ' + info.url.substring(info.url.lastIndexOf('#cookie=') + 8);
            }
        }
    },
    {urls: ['<all_urls>']},
    ["blocking"]
);