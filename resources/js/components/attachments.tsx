import { router, useForm } from '@inertiajs/react';
import { FileText, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import AttachmentController from '@/actions/App/Http/Controllers/AttachmentController';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { firstError } from '@/lib/utils';
import type { Attachment } from '@/types';

/**
 * Files attached to a company or contact. `storeUrl` is where uploads are
 * posted, or null when the user can't manage the record's files.
 */
export default function Attachments({
    attachments,
    storeUrl,
    maxSizeMb,
}: {
    attachments: Attachment[];
    storeUrl: string | null;
    maxSizeMb: number;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{ uploads: File[] }>({ uploads: [] });
    const [deleting, setDeleting] = useState<Attachment | null>(null);
    const [deletingInProgress, setDeletingInProgress] = useState(false);

    const upload = (event: React.FormEvent) => {
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

        router.delete(AttachmentController.destroy.url(deleting.id), {
            preserveScroll: true,
            onStart: () => setDeletingInProgress(true),
            onFinish: () => setDeletingInProgress(false),
            onSuccess: () => setDeleting(null),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Attachments</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {storeUrl && (
                    <form onSubmit={upload} className="space-y-3">
                        <div className="grid gap-2">
                            <Label htmlFor="attachment-uploads">
                                Upload files
                            </Label>
                            <Input
                                ref={fileInput}
                                id="attachment-uploads"
                                type="file"
                                multiple
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
                            <p className="text-sm text-muted-foreground">
                                Documents and images up to {maxSizeMb} MB.
                            </p>
                        </div>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            Upload
                        </Button>
                    </form>
                )}

                <ul className="space-y-3">
                    {attachments.map((attachment) => (
                        <li
                            key={attachment.id}
                            className="flex items-center gap-3"
                        >
                            {attachment.thumb_url ? (
                                <img
                                    src={attachment.thumb_url}
                                    alt=""
                                    className="size-12 rounded object-cover"
                                    loading="lazy"
                                />
                            ) : (
                                <div className="flex size-12 items-center justify-center rounded bg-muted">
                                    <FileText className="size-6 text-muted-foreground" />
                                </div>
                            )}

                            <div className="min-w-0 flex-1">
                                <a
                                    href={attachment.url}
                                    className="block truncate text-sm font-medium hover:underline"
                                >
                                    {attachment.file_name}
                                </a>
                                <p className="text-sm text-muted-foreground">
                                    {attachment.size} ·{' '}
                                    {attachment.created_at_diff}
                                </p>
                            </div>

                            {storeUrl && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Delete"
                                    onClick={() => setDeleting(attachment)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </li>
                    ))}

                    {attachments.length === 0 && (
                        <li className="text-sm text-muted-foreground">
                            No files yet.
                        </li>
                    )}
                </ul>
            </CardContent>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={`Delete ${deleting?.file_name ?? ''}?`}
                description="The file is removed permanently."
                processing={deletingInProgress}
                onConfirm={destroy}
            />
        </Card>
    );
}
