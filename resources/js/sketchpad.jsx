import React, { useCallback, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Excalidraw, serializeAsJSON, getSceneVersion } from '@excalidraw/excalidraw';
import '@excalidraw/excalidraw/index.css';

const mount = document.getElementById('sketchpad-root');

function readInitialScene() {
    const raw = document.getElementById('sketchpad-initial')?.textContent?.trim();
    if (!raw) return undefined;
    try {
        const scene = JSON.parse(raw);
        return {
            elements: scene.elements ?? [],
            appState: { ...(scene.appState ?? {}), collaborators: undefined },
            files: scene.files ?? undefined,
        };
    } catch {
        return undefined;
    }
}

const initialScene = readInitialScene();

function Sketchpad({ saveUrl, csrf }) {
    const apiRef = useRef(null);
    const timerRef = useRef(null);
    // Version of the elements we last persisted — start at the loaded scene so the
    // onChange Excalidraw fires on mount (and on pure selection) doesn't trigger a save.
    const savedVersionRef = useRef(getSceneVersion(initialScene?.elements ?? []));
    const [status, setStatus] = useState('');

    const persist = useCallback(async () => {
        const api = apiRef.current;
        if (!api) return;

        setStatus('Saving…');
        const elements = api.getSceneElements();
        const scene = serializeAsJSON(elements, api.getAppState(), api.getFiles(), 'local');

        try {
            const res = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ scene }),
            });
            if (res.ok) {
                savedVersionRef.current = getSceneVersion(elements);
                setStatus('Saved ✓');
            } else {
                setStatus('Save failed');
            }
        } catch {
            setStatus('Save failed');
        }
        window.clearTimeout(timerRef.current);
        timerRef.current = window.setTimeout(() => setStatus(''), 2500);
    }, [saveUrl, csrf]);

    // Debounced autosave — only when the drawing actually changed (ignores mount,
    // selection, and viewport-only changes), plus the explicit Save button.
    const onChange = useCallback(
        (elements) => {
            if (getSceneVersion(elements) === savedVersionRef.current) return;
            setStatus('Editing…');
            window.clearTimeout(timerRef.current);
            timerRef.current = window.setTimeout(persist, 1500);
        },
        [persist],
    );

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center gap-3 border-b border-gray-200 bg-white px-3 py-2">
                <button
                    type="button"
                    onClick={persist}
                    className="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
                >
                    Save
                </button>
                <span className="text-sm text-gray-500">{status}</span>
                <span className="ml-auto text-xs text-gray-400">Autosaves as you draw</span>
            </div>
            <div className="min-h-0 flex-1">
                <Excalidraw
                    excalidrawAPI={(api) => (apiRef.current = api)}
                    initialData={initialScene}
                    onChange={onChange}
                />
            </div>
        </div>
    );
}

if (mount) {
    createRoot(mount).render(
        <Sketchpad saveUrl={mount.dataset.saveUrl} csrf={mount.dataset.csrf} />,
    );
}
