// Attach dark mode toggle event listener.
document
  .getElementById("dark-mode-toggle")
  .addEventListener("change", toggleDarkMode);

// Set light or dark mode depending on the state of the checkbox.
function toggleDarkMode(event) {
  if (event.target.checked) {
    document.documentElement.setAttribute("data-theme", "dark");
  } else {
    document.documentElement.setAttribute("data-theme", "light");
  }
}
