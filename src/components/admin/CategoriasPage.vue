<script setup lang="ts">
import CrudPage from './CrudPage.vue'
import type { CrudConfig } from './CrudPage.vue'

const config: CrudConfig = {
  title: 'Categorías de Producto',
  description: 'Gestiona las categorías del catálogo',
  entityLabel: 'Categoría',
  entityLabelPlural: 'Categorías',
  apiEndpoint: 'categorias',
  columns: [
    { key: 'name', label: 'Nombre' },
    { key: 'slug', label: 'Slug', class: 'font-mono text-xs' },
    {
      key: 'productCount',
      label: 'Productos',
      render: (item: any) => item.productCount ?? '—',
      class: 'text-center',
    },
  ],
  formFields: [
    {
      key: 'name',
      label: 'Nombre',
      type: 'text',
      required: true,
      maxlength: 100,
      placeholder: 'Ej. Zapatos de Tacón',
    },
  ],
}
</script>

<template>
  <CrudPage :config="config" />
</template>
