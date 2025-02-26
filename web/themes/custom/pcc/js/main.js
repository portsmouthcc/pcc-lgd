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

/**
 * Code to toggle element visibility for services menu and search bar.
 */

// Get all the toggle button elements as an array.
const toggleButtons = Array.from(
  document.getElementsByClassName("toggle-button"),
);

// Loop through toggle buttons.
for (const button of toggleButtons) {
  // Get the element to show/hide.
  const target = document.getElementById(button.dataset.target);

  if (target) {
    target.classList.add("toggle-target");

    // Assign the click event.
    button.onclick = () => {
      // Hide all target elements that aren't this one.
      for (const t of document.getElementsByClassName("toggle-target")) {
        if (t !== target) {
          t.classList.remove("active");
        }
      }

      // Toggle the active CSS class on the target.
      target.classList.toggle("active");
    };
  }
}
