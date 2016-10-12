// http://stackoverflow.com/questions/16133614/chrome-extensions-that-runs-js-before-every-page-loads-using-content-script
// https://developer.chrome.com/extensions/getstarted
// http://stackoverflow.com/questions/20323600/how-to-get-errors-stack-trace-in-chrome-extension-content-script

localStorage.setItem("js_errors", "");

window.addEventListener("error", function (error) {
    message = error.message + ' (filename: ' + error.filename + '::' + error.lineno + ')';
    console.log('Leankoala: ' + message);
    if (localStorage.getItem("js_errors") == "") {
        localStorage.setItem("js_errors", message);
    } else {
        localStorage.setItem("js_errors", localStorage.getItem("js_errors") + ' ### ' + message);
    }
});