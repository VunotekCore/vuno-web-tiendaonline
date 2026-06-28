<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

const api = useApi()
const toast = useToast()

const props = defineProps<{ postId?: string }>()

const isEdit = !!props.postId

const TITLE_MAX = 255
const SLUG_MAX = 255
const EXCERPT_MAX = 500
const AUTHOR_MAX = 100

const loading = ref(isEdit)
const saving = ref(false)
const categories = ref<Array<{ id: number; name: string }>>([])

const title = ref('')
const slug = ref('')
const excerpt = ref('')
const content = ref('')
const thumbnailImage = ref('')
const featuredImage = ref('')
const author = ref('')
const categoryId = ref('')
const status = ref('draft')
const metaTitle = ref('')
const metaDescription = ref('')

let slugManual = false
let quillInstance: any = null

onMounted(async () => {
  try {
    const data = await api.get<Array<{ id: number; name: string }>>('/api/blog/categories.php')
    categories.value = Array.isArray(data) ? data : []
  } catch {}

  if (isEdit && props.postId) {
    try {
      const post = await api.get<any>(`/api/blog/get.php?id=${props.postId}`)
      title.value = post.title || ''
      slug.value = post.slug || ''
      excerpt.value = post.excerpt || ''
      content.value = post.content || ''
      thumbnailImage.value = post.thumbnail_image || ''
      featuredImage.value = post.featured_image || ''
      author.value = post.author || 'Ram;Lop'
      categoryId.value = post.category_id ? String(post.category_id) : ''
      status.value = post.status || 'draft'
      metaTitle.value = post.meta_title || ''
      metaDescription.value = post.meta_description || ''
      slugManual = !!post.slug

      if (content.value && quillInstance) {
        quillInstance.clipboard.dangerouslyPasteHTML(content.value)
      }
    } catch (e: any) {
      toast.error(e.message || 'Error loading post')
    } finally {
      loading.value = false
    }
  } else {
    loading.value = false
  }
})

function initQuill(el: HTMLElement) {
  if (!el || quillInstance) return
  const Quill = (window as any).Quill
  if (!Quill) {
    const check = setInterval(() => {
      if ((window as any).Quill) {
        clearInterval(check)
        initQuill(el)
      }
    }, 100)
    return
  }
  quillInstance = new Quill(el, {
    theme: 'snow',
    modules: {
      toolbar: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote', 'link'],
        ['clean'],
      ],
    },
    placeholder: 'Escribe tu contenido aquí...',
  })

  if (isEdit && content.value) {
    quillInstance.clipboard.dangerouslyPasteHTML(content.value)
  }

  quillInstance.on('text-change', () => {
    const html = quillInstance.root.innerHTML
    const isEmpty = !html || html === '<p><br></p>' || html.trim() === ''
    content.value = isEmpty ? '' : html
  })
}

function slugify(text: string): string {
  return text.toLowerCase().trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
}

function onTitleInput() {
  if (!slugManual) {
    slug.value = slugify(title.value)
  }
}

function onSlugInput() {
  slugManual = !!slug.value
}

function countClass(len: number, max: number) {
  if (len >= max) return 'text-[#DC2626]'
  if (len >= max * 0.8) return 'text-[#B8956A]'
  return 'text-[#94a3b8]'
}

function uploadImage(folder: string, file: File, field: 'thumbnail' | 'featured') {
  const btn = document.activeElement as HTMLElement
  const origText = btn?.textContent || 'Subir'
  if (btn) { btn.textContent = 'Subiendo...'; (btn as HTMLButtonElement).disabled = true }

  const fd = new FormData()
  fd.append('file', file)
  fd.append('folder', folder)

  fetch('/api/imagekit/upload.php', { method: 'POST', body: fd, credentials: 'include' })
    .then(r => r.json())
    .then(data => {
      if (field === 'thumbnail') thumbnailImage.value = data.url
      else featuredImage.value = data.url
    })
    .catch(() => toast.error('Error al subir imagen'))
    .finally(() => {
      if (btn) { btn.textContent = origText; (btn as HTMLButtonElement).disabled = false }
    })
}

function removeImage(field: 'thumbnail' | 'featured') {
  if (field === 'thumbnail') thumbnailImage.value = ''
  else featuredImage.value = ''
}

function onFileChange(e: Event, field: 'thumbnail' | 'featured') {
  const input = e.target as HTMLInputElement
  const file = input?.files?.[0]
  if (!file) return
  uploadImage('blog', file, field)
  input.value = ''
}

async function handleSubmit() {
  const html = content.value

  if (!title.value.trim() || !slug.value.trim() || !html) {
    toast.error('Completa los campos obligatorios: título, slug y contenido.')
    return
  }

  saving.value = true
  try {
    const payload: Record<string, any> = {
      title: title.value.trim(),
      slug: slug.value.trim(),
      excerpt: excerpt.value.trim(),
      content: html,
      thumbnail_image: thumbnailImage.value,
      featured_image: featuredImage.value,
      author: author.value.trim() || 'Ram;Lop',
      category_id: categoryId.value || null,
      status: status.value,
      meta_title: metaTitle.value.trim(),
      meta_description: metaDescription.value.trim(),
    }

    if (isEdit) {
      payload.id = parseInt(props.postId!)
      await api.post('/api/blog/update.php', payload)
      toast.success('Post actualizado')
    } else {
      await api.post('/api/blog/create.php', payload)
      toast.success('Post creado')
    }
    window.location.href = '/admin/blog'
  } catch (e: any) {
    toast.error(e.message || 'Error al guardar')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="loading" class="font-body text-body-md text-[#94a3b8] p-4">Cargando...</div>

  <form v-else class="max-w-4xl" @submit.prevent="handleSubmit">
    <div class="admin-card space-y-6">
      <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
        <span class="material-symbols-outlined text-xl">article</span>
        {{ isEdit ? 'Editar Post' : 'Nuevo Post' }}
      </h2>

      <div>
        <label class="block text-sm font-medium text-[#94a3b8] mb-2">TÍTULO *</label>
        <div class="relative">
          <input v-model="title" type="text" required :maxlength="TITLE_MAX" class="admin-input pr-16" @input="onTitleInput" />
          <span class="absolute right-2 bottom-2 text-xs pointer-events-none" :class="countClass(title.length, TITLE_MAX)">{{ title.length }}/{{ TITLE_MAX }}</span>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-[#94a3b8] mb-2">SLUG *</label>
        <div class="relative">
          <input v-model="slug" type="text" required :maxlength="SLUG_MAX" class="admin-input pr-16 font-mono text-sm" @input="onSlugInput" />
          <span class="absolute right-2 bottom-2 text-xs pointer-events-none" :class="countClass(slug.length, SLUG_MAX)">{{ slug.length }}/{{ SLUG_MAX }}</span>
        </div>
        <p class="text-xs text-[#64748b] mt-1">Se genera automáticamente desde el título</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-[#94a3b8] mb-2">TEXTO INTRODUCTORIO</label>
        <div class="relative">
          <textarea v-model="excerpt" :maxlength="EXCERPT_MAX" rows="3" class="admin-input pr-16 resize-none"></textarea>
          <span class="absolute right-2 bottom-2 text-xs pointer-events-none" :class="countClass(excerpt.length, EXCERPT_MAX)">{{ excerpt.length }}/{{ EXCERPT_MAX }}</span>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-[#94a3b8] mb-2">CONTENIDO *</label>
        <div>
          <link rel="stylesheet" href="/quill/quill.snow.css" />
          <div :ref="initQuill" class="border border-[#1e293b] rounded-sm" style="min-height: 380px; background: #fff; color: #1a1a1a;"></div>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-[#94a3b8] mb-2">IMAGEN MINIATURA</label>
        <div class="border border-dashed border-[#1e293b] rounded-sm p-4">
          <div class="flex items-center gap-4">
            <button type="button" class="admin-btn admin-btn-secondary text-sm" @click="$refs.thumbnailInput?.click()">SUBIR IMAGEN</button>
            <input ref="thumbnailInput" type="file" class="hidden" accept="image/jpeg,image/png,image/webp" @change="onFileChange($event, 'thumbnail')" />
            <span class="text-xs text-[#94a3b8]">JPEG, PNG o WebP</span>
          </div>
          <div v-if="thumbnailImage" class="mt-3 flex items-center gap-3">
            <img :src="thumbnailImage" class="w-24 h-[72px] object-cover rounded-sm border border-[#1e293b]" alt="Preview miniatura" />
            <button type="button" class="text-xs font-medium text-[#DC2626] hover:text-red-400 transition-colors" @click="removeImage('thumbnail')">ELIMINAR</button>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-[#94a3b8] mb-2">IMAGEN DESTACADA</label>
        <div class="border border-dashed border-[#1e293b] rounded-sm p-4">
          <div class="flex items-center gap-4">
            <button type="button" class="admin-btn admin-btn-secondary text-sm" @click="$refs.featuredInput?.click()">SUBIR IMAGEN</button>
            <input ref="featuredInput" type="file" class="hidden" accept="image/jpeg,image/png,image/webp" @change="onFileChange($event, 'featured')" />
            <span class="text-xs text-[#94a3b8]">JPEG, PNG o WebP</span>
          </div>
          <div v-if="featuredImage" class="mt-3 flex items-center gap-3">
            <img :src="featuredImage" class="w-24 h-[72px] object-cover rounded-sm border border-[#1e293b]" alt="Preview destacada" />
            <button type="button" class="text-xs font-medium text-[#DC2626] hover:text-red-400 transition-colors" @click="removeImage('featured')">ELIMINAR</button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
          <label class="block text-sm font-medium text-[#94a3b8] mb-2">AUTOR</label>
          <div class="relative">
            <input v-model="author" type="text" :maxlength="AUTHOR_MAX" class="admin-input pr-14" placeholder="Ram;Lop" />
            <span class="absolute right-2 bottom-2 text-xs pointer-events-none" :class="countClass(author.length, AUTHOR_MAX)">{{ author.length }}/{{ AUTHOR_MAX }}</span>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-[#94a3b8] mb-2">CATEGORÍA</label>
          <select v-model="categoryId" class="admin-input">
            <option value="">Sin categoría</option>
            <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-[#94a3b8] mb-2">ESTADO</label>
          <select v-model="status" class="admin-input">
            <option value="draft">Borrador</option>
            <option value="published">Publicado</option>
          </select>
        </div>
      </div>

      <div class="pt-6 mt-6 border-t border-[#1e293b]">
        <h3 class="text-sm font-semibold text-[#94a3b8] mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-lg">travel_explore</span>
          SEO & Open Graph
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-[#94a3b8] mb-2">META TITLE</label>
            <input v-model="metaTitle" type="text" maxlength="70" class="admin-input" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-[#94a3b8] mb-2">META DESCRIPTION</label>
            <textarea v-model="metaDescription" maxlength="160" rows="2" class="admin-input resize-none"></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="flex justify-end gap-3 mt-8">
      <a href="/admin/blog" class="admin-btn admin-btn-secondary">CANCELAR</a>
      <button type="submit" class="admin-btn admin-btn-primary" :disabled="saving">
        <span class="material-symbols-outlined text-base">{{ saving ? 'progress_activity' : 'save' }}</span>
        {{ saving ? 'GUARDANDO...' : 'GUARDAR' }}
      </button>
    </div>
  </form>
</template>
