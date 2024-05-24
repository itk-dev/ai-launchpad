function openGlobalNavigation() {
  console.log("Open");
  document.getElementById('globalNavigation').classList.remove("hidden");
}

function closeGlobalNavigation() {
  console.log("Close");
  document.getElementById('globalNavigation').classList.add("hidden");
}

document.getElementById('btnOpenGlobalNavigation').addEventListener('click', openGlobalNavigation);
document.getElementById('btnCloseGlobalNavigation').addEventListener('click', closeGlobalNavigation);
