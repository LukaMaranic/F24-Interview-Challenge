import React, { useEffect, useState } from 'react'

async function api(path, options) {
  const response = await fetch(path, options)

  if (!response.ok) {
    throw new Error('Request failed')
  }

  return response.status === 204 ? null : response.json()
}

export default function App() {
  const [currentFolder, setCurrentFolder] = useState({ id: 1, name: 'root' })
  const [breadcrumbs, setBreadcrumbs] = useState([{ id: 1, name: 'root' }])
  const [entries, setEntries] = useState({ folders: [], files: [] })
  const [search, setSearch] = useState('')
  const [suggestions, setSuggestions] = useState([])
  const [results, setResults] = useState([])
  const [error, setError] = useState('')

  async function loadFolder() {
    try {
      setEntries(await api(`/api/folders/${currentFolder.id}/entries`))
      setError('')
    } catch {
      setError('Could not load this folder.')
    }
  }

  useEffect(() => {
    loadFolder()
  }, [currentFolder.id])

  useEffect(() => {
    if (!search) {
      setSuggestions([])
      setResults([])
      return
    }

    const timeout = setTimeout(async () => {
      try {
        setSuggestions(await api(`/api/files/suggestions?prefix=${encodeURIComponent(search)}`))
      } catch {
        setSuggestions([])
      }
    }, 200)

    return () => clearTimeout(timeout)
  }, [search])

  async function createFolder() {
    const name = window.prompt('Folder name')

    if (!name) return

    await api('/api/folders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ parent_id: currentFolder.id, name }),
    })
    loadFolder()
  }

  async function createFile() {
    const name = window.prompt('File name')

    if (!name) return

    await api('/api/files', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ folder_id: currentFolder.id, name }),
    })
    loadFolder()
  }

  async function deleteEntry(kind, id) {
    await api(`/api/${kind}s/${id}`, { method: 'DELETE' })
    loadFolder()
  }

  function openFolder(folder) {
    setCurrentFolder(folder)
    setBreadcrumbs([...breadcrumbs, folder])
  }

  function openBreadcrumb(folder, index) {
    setCurrentFolder(folder)
    setBreadcrumbs(breadcrumbs.slice(0, index + 1))
  }

  async function exactSearch(event) {
    event.preventDefault()
    setResults(await api(`/api/files?name=${encodeURIComponent(search)}`))
    setSuggestions([])
  }

  return (
    <main>
      <header>
        <h1>F24 Drive</h1>
        <form className="search" onSubmit={exactSearch}>
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search files"
          />
          <button type="submit">Search</button>
          {suggestions.length > 0 && (
            <div className="suggestions">
              {suggestions.map((file) => (
                <button key={file.id} type="button" onClick={() => setSearch(file.name)}>
                  {file.name}
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
          {entries.folders.length === 0 && entries.files.length === 0 && (
            <p className="empty">This folder is empty.</p>
          )}
        </div>
      </section>

      {results.length > 0 && (
        <section className="results">
          <h2>Search results</h2>
          {results.map((file) => <p key={file.id}>📄 {file.name} · folder {file.folder_id}</p>)}
        </section>
      )}
    </main>
  )
}
