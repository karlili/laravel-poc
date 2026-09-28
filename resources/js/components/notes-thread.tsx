import { router, useForm } from '@inertiajs/react';
import { Paperclip, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import NoteController from '@/actions/App/Http/Controllers/NoteController';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { firstError } from '@/lib/utils';
import type { Note } from '@/types';

/**
 * Notes on a company or contact. `storeUrl` is where new notes are posted,
 * or null when the user can't add notes.
 */
export default function NotesThread({
    notes,
    storeUrl,
}: {
    notes: Note[];
    storeUrl: string | null;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{ body: string; uploads: File[] }>({
        body: '',
        uploads: [],
    });
    const [deleting, setDeleting] = useState<Note | null>(null);
    const [deletingInProgress, setDeletingInProgress] = useState(false);

    const add = (event: React.FormEvent) => {
        event.preventDefault();

        if (!storeUrl) {
            return;
        }

        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    const destroy = () => {
        if (!deleting) {
            return;
        }

        router.delete(NoteController.destroy.url(deleting.id), {
            preserveScroll: true,
            onStart: () => setDeletingInProgress(true),
            onFinish: () => setDeletingInProgress(false),
            onSuccess: () => setDeleting(null),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Notes</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {storeUrl && (
                    <form onSubmit={add} className="space-y-3">
                        <div className="grid gap-2">
                            <Label htmlFor="note-body">Add a note</Label>
                            <Textarea
                                id="note-body"
                                rows={3}
                                value={form.data.body}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                                aria-invalid={
                                    form.errors.body ? true : undefined
                                }
                            />
                            <InputError message={form.errors.body} />
                        </div>
                        <div className="grid gap-2">
                            <Input
                                ref={fileInput}
                                type="file"
                                multiple
                                aria-label="Attach files"
                                onChange={(event) =>
                                    form.setData(
                                        'uploads',
                                        Array.from(event.target.files ?? []),
                                    )
                                }
                            />
                            <InputError
                                message={firstError(form.errors, 'uploads')}
                            />
                        </div>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            Add note
                        </Button>
                    </form>
                )}

                <div className="space-y-4">
                    {notes.map((note) => (
                        <div
                            key={note.id}
                            className="border-t pt-4 first:border-0 first:pt-0"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-sm text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        {note.author?.name ?? 'Former user'}
                                    </span>{' '}
                                    · {note.created_at_diff}
                                </p>

                                {note.can.delete && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        aria-label="Delete note"
                                        onClick={() => setDeleting(note)}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </div>

                            <p className="mt-1 text-sm whitespace-pre-line">
                                {note.body}
                            </p>

                            {note.attachments.length > 0 && (
                                <ul className="mt-2 flex flex-wrap gap-2">
                                    {note.attachments.map((attachment) => (
                                        <li key={attachment.id}>
                                            <Badge variant="secondary" asChild>
                                                <a href={attachment.url}>
                                                    <Paperclip />
                                                    {attachment.file_name}
                                                </a>
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    ))}

                    {notes.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No notes yet.
                        </p>
                    )}
                </div>
            </CardContent>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Delete this note?"
                description="The note and its attached files are removed."
                processing={deletingInProgress}
                onConfirm={destroy}
            />
        </Card>
    );
}
