import React, { useEffect, useState } from 'react'

async function api(path, options) {
  const response = await fetch(path, options)
  const data = response.status === 204 ? null : await response.json()

  if (!response.ok) {
    throw new Error(data?.error || 'Request failed')
  }

  return data
}

export default function App() {
  const [currentFolder, setCurrentFolder] = useState({ id: 1, name: 'root' })
  const [breadcrumbs, setBreadcrumbs] = useState([{ id: 1, name: 'root' }])
  const [entries, setEntries] = useState({ folders: [], files: [] })
  const [nextPage, setNextPage] = useState({ folder: null, file: null })
  const [search, setSearch] = useState('')
  const [searchScope, setSearchScope] = useState('all')
  const [suggestions, setSuggestions] = useState([])
  const [results, setResults] = useState([])
  const [searchAttempted, setSearchAttempted] = useState(false)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  function searchPath(path, key, value) {
    const parameters = new URLSearchParams({ [key]: value })

    if (searchScope === 'current') {
      parameters.set('folder_id', currentFolder.id)
    }

    return `${path}?${parameters}`
  }

  async function loadFolder(append = false) {
    const parameters = new URLSearchParams({ limit: '50' })

    if (append) {
      nextPage.folder === null
        ? parameters.set('folders_done', '1')
        : parameters.set('folder_after_id', nextPage.folder)
      nextPage.file === null
        ? parameters.set('files_done', '1')
        : parameters.set('file_after_id', nextPage.file)
    }

    try {
      setLoading(true)
      const data = await api(`/api/folders/${currentFolder.id}/entries?${parameters}`)
      setEntries((current) => append
        ? {
            folders: [...current.folders, ...data.folders],
            files: [...current.files, ...data.files],
          }
        : { folders: data.folders, files: data.files })
      setNextPage({
        folder: data.next_folder_after_id,
        file: data.next_file_after_id,
      })
      setError('')
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadFolder()
  }, [currentFolder.id])

  useEffect(() => {
    setResults([])
    setSearchAttempted(false)

    if (!search) {
      setSuggestions([])
      return
    }

    const timeout = setTimeout(async () => {
      try {
        setSuggestions(await api(searchPath('/api/files/suggestions', 'prefix', search)))
      } catch {
        setSuggestions([])
      }
    }, 200)

    return () => clearTimeout(timeout)
  }, [search, searchScope, currentFolder.id])

  async function createFolder() {
    const name = window.prompt('Folder name')

    if (!name) return

    try {
      await api('/api/folders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ parent_id: currentFolder.id, name }),
      })
      loadFolder()
    } catch (requestError) {
      setError(requestError.message)
    }
  }

  async function createFile() {
    const name = window.prompt('File name')

    if (!name) return

    try {
      await api('/api/files', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder_id: currentFolder.id, name }),
      })
      loadFolder()
    } catch (requestError) {
      setError(requestError.message)
    }
  }

  async function deleteEntry(kind, id) {
    if (!window.confirm(`Delete this ${kind}?`)) return

    try {
      await api(`/api/${kind}s/${id}`, { method: 'DELETE' })
      loadFolder()
    } catch (requestError) {
      setError(requestError.message)
    }
  }

  function openFolder(folder) {
    setCurrentFolder(folder)
    setBreadcrumbs([...breadcrumbs, folder])
  }

  function openBreadcrumb(folder, index) {
    setCurrentFolder(folder)
    setBreadcrumbs(breadcrumbs.slice(0, index + 1))
  }

  async function runExactSearch(name) {
    try {
      setResults(await api(searchPath('/api/files', 'name', name)))
      setSuggestions([])
      setSearchAttempted(true)
      setError('')
    } catch (requestError) {
      setError(requestError.message)
    }
  }

  function exactSearch(event) {
    event.preventDefault()

    if (search) {
      runExactSearch(search)
    }
  }

  function selectSuggestion(file) {
    setSearch(file.name)
    runExactSearch(file.name)
  }

  const hasMore = nextPage.folder !== null || nextPage.file !== null

  return (
    <main>
      <header>
        <h1>F24 Drive</h1>
        <form className="search" onSubmit={exactSearch}>
          <select value={searchScope} onChange={(event) => setSearchScope(event.target.value)}>
            <option value="all">All files</option>
            <option value="current">Current folder</option>
          </select>
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search files"
          />
          <button type="submit">Search</button>
          {suggestions.length > 0 && (
            <div className="suggestions">
              {suggestions.map((file) => (
                <button key={file.id} type="button" onClick={() => selectSuggestion(file)}>
                  <span>{file.name}</span>
                  <small>{file.folder_name}</small>
                </button>
              ))}
            </div>
          )}
        </form>
      </header>

      <section className="browser">
        <nav className="breadcrumbs">
          {breadcrumbs.map((folder, index) => (
            <React.Fragment key={folder.id}>
              {index > 0 && <span>/</span>}
              <button onClick={() => openBreadcrumb(folder, index)}>{folder.name}</button>
            </React.Fragment>
          ))}
        </nav>

        <div className="toolbar">
          <button onClick={createFolder}>New folder</button>
          <button onClick={createFile}>New file</button>
        </div>

        {error && <p className="error">{error}</p>}

        <div className="entries">
          {entries.folders.map((folder) => (
            <div className="entry" key={`folder-${folder.id}`}>
              <button className="entry-name" onClick={() => openFolder(folder)}>
                <span>📁</span> {folder.name}
              </button>
              <button className="delete" onClick={() => deleteEntry('folder', folder.id)}>Delete</button>
            </div>
          ))}
          {entries.files.map((file) => (
            <div className="entry" key={`file-${file.id}`}>
              <span className="entry-name"><span>📄</span> {file.name}</span>
              <button className="delete" onClick={() => deleteEntry('file', file.id)}>Delete</button>
            </div>
          ))}
          {!loading && entries.folders.length === 0 && entries.files.length === 0 && (
            <p className="empty">This folder is empty.</p>
          )}
        </div>

        {loading && <p className="loading">Loading…</p>}
        {!loading && hasMore && <button className="load-more" onClick={() => loadFolder(true)}>Load more</button>}
      </section>

      {searchAttempted && (
        <section className="results">
          <h2>Search results</h2>
          {results.length === 0
            ? <p>No exact matches found.</p>
            : results.map((file) => <p key={file.id}>📄 {file.name} · {file.folder_name}</p>)}
        </section>
      )}
    </main>
  )
}
