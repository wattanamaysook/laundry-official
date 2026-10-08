// ========================================
// MAIN JAVASCRIPT
// ========================================

document.addEventListener("DOMContentLoaded", function () {

    console.log("Laundry Official loaded");

});

document.addEventListener("click", function (event) {
    const link = event.target.closest("a[href]");

    if (
        !link ||
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey ||
        link.target === "_blank" ||
        link.hasAttribute("download") ||
        document.body.classList.contains("is-leaving") ||
        window.matchMedia("(prefers-reduced-motion: reduce)").matches
    ) {
        return;
    }

    const destination = new URL(link.href, window.location.href);

    if (
        destination.origin !== window.location.origin ||
        (destination.pathname === window.location.pathname &&
            destination.search === window.location.search &&
            destination.hash)
    ) {
        return;
    }

    event.preventDefault();
    document.body.classList.add("is-leaving");

    window.setTimeout(function () {
        window.location.assign(destination.href);
    }, 160);
});

function mockPayment() {

    alert("จำลองการชำระเงินสำเร็จ");

}
