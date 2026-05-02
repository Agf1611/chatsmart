$(document).ready(function () {
  document.querySelectorAll(".page-footer, .footer").forEach(function (node) {
    if (node && node.parentNode) {
      node.parentNode.removeChild(node);
    }
  });
});
