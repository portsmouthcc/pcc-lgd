// Attach dark mode toggle event listener.
document
  .getElementById("dark-mode-toggle")
  .addEventListener("change", on_dark_mode_toggle_change);

// Set light or dark mode depending on the state of the checkbox.
function on_dark_mode_toggle_change(event) {
  if (event.target.checked) {
    document.documentElement.setAttribute("data-theme", "dark");
  } else {
    document.documentElement.setAttribute("data-theme", "light");
  }
}
