document.addEventListener("DOMContentLoaded", function () {
  const forms = document.querySelectorAll("form");
  forms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      const inputs = form.querySelectorAll("input[required], select[required]");
      let valid = true;
      inputs.forEach((input) => {
        if (!input.value) {
          valid = false;
          input.style.border = "1px solid red";
        } else {
          input.style.border = "";
        }
      });
      if (!valid) {
        e.preventDefault();
        alert("Please fill out all required fields.");
      }
    });
  });
});
