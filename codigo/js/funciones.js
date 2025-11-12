// funciones.js — demo sin BD
document.addEventListener('DOMContentLoaded', () => {
  console.log('funciones.js cargado correctamente ✅');

  const alerta = document.querySelector('[data-flash]');
  if (alerta) {
    alerta.style.transition = 'opacity 0.8s';
    setTimeout(() => alerta.style.opacity = '0', 3000);
  }
});
