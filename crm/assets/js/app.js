document.addEventListener("DOMContentLoaded", () => {
  const userMenuButton = document.getElementById("userMenuButton");
  const userMenu = document.getElementById("userMenu");
  if (userMenuButton && userMenu) {
    userMenuButton.addEventListener("click", (e) => {
      e.stopPropagation();
      userMenu.classList.toggle("hidden");
    });
    document.addEventListener("click", (e) => {
      if (!userMenu.contains(e.target) && !userMenuButton.contains(e.target)) {
        userMenu.classList.add("hidden");
      }
    });
  }

  // Lock screen
  window.lockScreen = function () {
    document.getElementById("lockScreenModal").classList.remove("hidden");
  };
  function setButtonLoading(button, loadingText = "در حال پردازش...") {
    const originalText = button.html();
    button.html(
      `<i class='bx bx-loader bx-spin ml-2 text-white'></i>${loadingText}`
    );
    button.prop("disabled", true);
    return originalText;
  }

  function resetButton(button, originalText) {
    button.html(originalText);
    button.prop("disabled", false);
  }
  window.unlockScreen = function () {
    const password = document.getElementById("unlockPassword").value;

    const button = $("#unlockScreen");
    const originalText = setButtonLoading(button);
    $.post("apis/unlock.php", { password: password }, function (data) {
      resetButton(button, originalText);
      data = JSON.parse(data);
      if (data.ok) {
        document.getElementById("lockScreenModal").classList.add("hidden");
      } else {
        $("#snackbar")
          .removeClass("bg-green-500")
          .addClass("bg-red-500")
          .text(data.error)
          .removeClass("hidden");
        setTimeout(() => $("#snackbar").addClass("hidden"), 3000);
      }
    });
  };

  // Loading functions
  window.showLoading = function () {
    document.getElementById("loadingOverlay").classList.remove("hidden");
  };
  window.hideLoading = function () {
    document.getElementById("loadingOverlay").classList.add("hidden");
  };
});

const themeIcon = document.getElementById("theme-icon");
const htmlElement = document.documentElement;
const themeToggle = document.getElementById("theme-toggle");

function setInitialTheme() {
  const savedTheme = localStorage.getItem("theme");
  if (savedTheme) {
    document.documentElement.classList.add(savedTheme);
  } else {
    const prefersDark = window.matchMedia(
      "(prefers-color-scheme: dark)"
    ).matches;
    const theme = prefersDark ? "dark" : "light";
    document.documentElement.classList.add(theme);
    localStorage.setItem("theme", theme);
  }
}
function updateIcon() {
  if (htmlElement.classList.contains("dark")) {
    themeIcon.classList.remove("bx-sun");
    themeIcon.classList.add("bx-moon");
    $("#theme-text").text("حالت روشن");
  } else {
    themeIcon.classList.remove("bx-moon");
    themeIcon.classList.add("bx-sun");
    $("#theme-text").text("حالت تاریک");
  }

}
setInitialTheme();
updateIcon();

themeToggle.addEventListener("click", () => {
  if (htmlElement.classList.contains("dark")) {
    htmlElement.classList.remove("dark");
    htmlElement.classList.add("light");
    localStorage.setItem("theme", "light");
  } else {
    htmlElement.classList.remove("light");
    htmlElement.classList.add("dark");
    localStorage.setItem("theme", "dark");
  }
  updateIcon();
});

$(document).ready(function () {
  $("#print-page").click(function printpage() {
    if ($("table").html()) {
      $("table").print({
        globalStyles: true,
        mediaPrint: false,
        stylesheet: null,
        noPrintSelector: ".no-print",
        iframe: true,
        append: null,
        prepend: null,
        manuallyCopyFormValues: true,
        deferred: $.Deferred(),
        timeout: 750,
        title: null,
        doctype: "<!doctype html>",
      });
    } else {
      alert("جدولی یافت نشد");
    }
  });
});
